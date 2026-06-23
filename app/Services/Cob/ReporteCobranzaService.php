<?php

namespace App\Services\Cob;

use App\Models\Cob\Estimacion;
use App\Models\Cob\Partida;
use App\Models\Cob\ReporteNota;
use App\Models\Obra;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cálculo en vivo del reporte semanal de cobranza. No hay snapshot: el saldo es
 * el acumulado real de cartera (Σ partidas de obras detonadas − Σ estimaciones
 * cobradas) a una fecha dada, por lo que el saldo anterior de una semana siempre
 * empata con el nuevo saldo de la semana previa. Lo único persistido son notas.
 */
class ReporteCobranzaService
{
    /** IVA fijo aplicado al monto de partidas de una obra detonada. */
    private const TASA_IVA = 0.16;

    /** Última semana ISO a reportar del año (la actual si es el año en curso). */
    public function maxSemana(int $anio): int
    {
        $hoy = Carbon::now();

        return $anio === (int) $hoy->isoWeekYear
            ? (int) $hoy->isoWeek
            : (int) Carbon::create($anio, 12, 28)->isoWeek;
    }

    /**
     * Rango [inicio, fin] de la semana ISO indicada.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function rangoSemana(int $anio, int $semana): array
    {
        $inicio = Carbon::now()->setISODate($anio, $semana)->startOfWeek();

        return [$inicio, $inicio->copy()->endOfWeek()];
    }

    /**
     * Saldo acumulado de cartera hasta la fecha $t (inclusive).
     *
     * @return array{sin: float, con: float}
     */
    public function saldoHasta(Carbon $t): array
    {
        $detSin = (float) Partida::query()
            ->whereHas('obra', fn ($q) => $q->sinPlanta()->where('created_at', '<=', $t))
            ->sum('monto');

        $cobSin = (float) Estimacion::query()
            ->where('estado', 'pagado')
            ->where('fecha_ultimo_cambio_estado', '<=', $t)
            ->sum('monto_estimado');

        $cobCon = (float) Estimacion::query()
            ->where('estado', 'pagado')
            ->where('fecha_ultimo_cambio_estado', '<=', $t)
            ->sum('monto_total');

        return [
            'sin' => round($detSin - $cobSin, 2),
            'con' => round($detSin * (1 + self::TASA_IVA) - $cobCon, 2),
        ];
    }

    /**
     * Obras (no planta) detonadas (creadas) en el rango, con su monto = Σ partidas.
     *
     * @return Collection<int, array{obra_id: int, obra_no: string, descripcion: string|null, monto_sin_iva: float, monto_con_iva: float}>
     */
    public function detonacionesSemana(Carbon $inicio, Carbon $fin): Collection
    {
        return Obra::query()
            ->sinPlanta()
            ->whereBetween('created_at', [$inicio, $fin])
            ->withSum('partidas as partidas_monto', 'monto')
            ->orderBy('no')
            ->get()
            ->map(function (Obra $obra): array {
                $sin = (float) ($obra->partidas_monto ?? 0);

                return [
                    'obra_id' => $obra->id,
                    'obra_no' => $obra->no,
                    'descripcion' => $obra->descripcion,
                    'monto_sin_iva' => round($sin, 2),
                    'monto_con_iva' => round($sin * (1 + self::TASA_IVA), 2),
                ];
            });
    }

    /**
     * Estimaciones cobradas (pasaron a 'pagado') en el rango.
     *
     * @return Collection<int, array{obra_id: int|null, obra_no: string, estimacion_id: int, numero_estimacion: int, monto_sin_iva: float, monto_con_iva: float}>
     */
    public function cobrosSemana(Carbon $inicio, Carbon $fin): Collection
    {
        return Estimacion::query()
            ->where('estado', 'pagado')
            ->whereBetween('fecha_ultimo_cambio_estado', [$inicio, $fin])
            ->with(['obra:id,no', 'proyecto:id,no'])
            ->orderBy('obra_id')
            ->orderBy('numero_estimacion')
            ->get()
            ->map(fn (Estimacion $est): array => [
                'obra_id' => $est->obra_id,
                // Las estimaciones globales (sin obra) se agrupan bajo el proyecto.
                'obra_no' => $est->obra?->no ?? ($est->proyecto ? "Global · {$est->proyecto->no}" : 'Global'),
                'estimacion_id' => $est->id,
                'numero_estimacion' => $est->numero_estimacion,
                'monto_sin_iva' => (float) $est->monto_estimado,
                'monto_con_iva' => (float) $est->monto_total,
            ]);
    }

    /**
     * Reporte completo de una semana (saldos, listas y totales), con sus notas.
     *
     * @return array<string, mixed>
     */
    public function semana(int $anio, int $semana): array
    {
        [$inicio, $fin] = $this->rangoSemana($anio, $semana);

        $saldoAnterior = $this->saldoHasta($inicio->copy()->subSecond());
        $detonaciones = $this->detonacionesSemana($inicio, $fin);
        $cobros = $this->cobrosSemana($inicio, $fin);

        $detSin = round((float) $detonaciones->sum('monto_sin_iva'), 2);
        $detCon = round((float) $detonaciones->sum('monto_con_iva'), 2);
        $cobSin = round((float) $cobros->sum('monto_sin_iva'), 2);
        $cobCon = round((float) $cobros->sum('monto_con_iva'), 2);

        return [
            'anio' => $anio,
            'semana' => $semana,
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $fin->toDateString(),
            'saldo_anterior_sin_iva' => $saldoAnterior['sin'],
            'saldo_anterior_con_iva' => $saldoAnterior['con'],
            'total_detonaciones_sin_iva' => $detSin,
            'total_detonaciones_con_iva' => $detCon,
            'total_cobrado_sin_iva' => $cobSin,
            'total_cobrado_con_iva' => $cobCon,
            'saldo_nuevo_sin_iva' => round($saldoAnterior['sin'] + $detSin - $cobSin, 2),
            'saldo_nuevo_con_iva' => round($saldoAnterior['con'] + $detCon - $cobCon, 2),
            'detonaciones' => $detonaciones->values(),
            'cobros' => $cobros->values(),
            'notas' => ReporteNota::where('anio', $anio)->where('semana', $semana)->value('notas'),
        ];
    }

    /**
     * Tabla del año: una fila por semana (1..maxSemana) con el saldo corriendo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tablaAnual(int $anio): array
    {
        $maxSemana = $this->maxSemana($anio);
        $inicioAnio = Carbon::now()->setISODate($anio, 1)->startOfWeek();
        $finAnio = Carbon::now()->setISODate($anio, $maxSemana)->endOfWeek();

        // Saldo acumulado antes de la primera semana del año.
        $saldo = $this->saldoHasta($inicioAnio->copy()->subSecond());

        // Movimientos del año agrupados por semana ISO (2 consultas).
        $detPorSemana = Obra::query()
            ->sinPlanta()
            ->whereBetween('created_at', [$inicioAnio, $finAnio])
            ->withSum('partidas as partidas_monto', 'monto')
            ->get(['id', 'created_at'])
            ->groupBy(fn (Obra $o) => (int) $o->created_at->isoWeek)
            ->map(fn ($obras) => (float) $obras->sum('partidas_monto'));

        $cobPorSemana = Estimacion::query()
            ->where('estado', 'pagado')
            ->whereBetween('fecha_ultimo_cambio_estado', [$inicioAnio, $finAnio])
            ->get(['fecha_ultimo_cambio_estado', 'monto_estimado', 'monto_total'])
            ->groupBy(fn (Estimacion $e) => (int) $e->fecha_ultimo_cambio_estado->isoWeek);

        $notas = ReporteNota::where('anio', $anio)
            ->pluck('notas', 'semana');

        $filas = [];
        for ($semana = 1; $semana <= $maxSemana; $semana++) {
            [$inicio, $fin] = $this->rangoSemana($anio, $semana);

            $cobSemana = $cobPorSemana->get($semana);
            $detSin = round((float) ($detPorSemana->get($semana) ?? 0), 2);
            $detCon = round($detSin * (1 + self::TASA_IVA), 2);
            $cobSin = round((float) ($cobSemana?->sum('monto_estimado') ?? 0), 2);
            $cobCon = round((float) ($cobSemana?->sum('monto_total') ?? 0), 2);

            $saldoAnteriorSin = $saldo['sin'];
            $saldoAnteriorCon = $saldo['con'];
            $saldoNuevoSin = round($saldoAnteriorSin + $detSin - $cobSin, 2);
            $saldoNuevoCon = round($saldoAnteriorCon + $detCon - $cobCon, 2);

            $filas[] = [
                'anio' => $anio,
                'semana' => $semana,
                'fecha_inicio' => $inicio->toDateString(),
                'fecha_fin' => $fin->toDateString(),
                'saldo_anterior_sin_iva' => $saldoAnteriorSin,
                'saldo_anterior_con_iva' => $saldoAnteriorCon,
                'total_detonaciones_sin_iva' => $detSin,
                'total_detonaciones_con_iva' => $detCon,
                'total_cobrado_sin_iva' => $cobSin,
                'total_cobrado_con_iva' => $cobCon,
                'saldo_nuevo_sin_iva' => $saldoNuevoSin,
                'saldo_nuevo_con_iva' => $saldoNuevoCon,
                'tiene_notas' => filled($notas[$semana] ?? null),
            ];

            $saldo = ['sin' => $saldoNuevoSin, 'con' => $saldoNuevoCon];
        }

        return array_reverse($filas);
    }
}
