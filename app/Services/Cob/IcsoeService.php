<?php

namespace App\Services\Cob;

use App\Enums\Cob\IcsoeEstatus;
use App\Models\Cob\IcsoeMes;
use App\Models\Cob\IcsoeSbcAnio;
use App\Models\Cob\IcsoeSeguimiento;
use App\Models\Obra;
use App\Models\Proyecto;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Orquesta el seguimiento ICSOE: lo crea, lo recalcula cuando el valor a
 * ejecutar del proyecto cambia y lo deja marcado para que cobranza lo verifique.
 */
class IcsoeService
{
    /**
     * Recálculos pendientes de este request, por proyecto. Evita que guardar 30
     * partidas de golpe dispare 30 rollups completos.
     *
     * @var array<int, string>
     */
    private static array $programados = [];

    /** Corta cualquier recálculo disparado desde dentro de otro recálculo. */
    private static bool $recalculando = false;

    public function __construct(
        private readonly ValorAEjecutarService $valorAEjecutar,
        private readonly IcsoeCalculadora $calculadora,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(Proyecto $proyecto, array $datos): IcsoeSeguimiento
    {
        return DB::transaction(function () use ($proyecto, $datos) {
            $seguimiento = new IcsoeSeguimiento([
                ...$datos,
                'proyecto_id' => $proyecto->id,
                'estatus' => IcsoeEstatus::Vigente,
            ]);
            $seguimiento->save();
            $seguimiento->setRelation('proyecto', $proyecto);

            // El alta no marca pendiente: el monto que se calcula aquí es el
            // punto de partida, no un cambio que alguien deba verificar.
            return $this->recalcular($seguimiento, detectarCambio: false);
        });
    }

    /**
     * Rehace el monto base, el desglose mensual y los totales. Conserva siempre
     * los días cotizados y el SBC aplicado que capturó el usuario.
     */
    public function recalcular(
        IcsoeSeguimiento $seguimiento,
        ?string $motivo = null,
        bool $detectarCambio = true,
    ): IcsoeSeguimiento {
        return DB::transaction(function () use ($seguimiento, $motivo, $detectarCambio) {
            $anterior = self::$recalculando;
            self::$recalculando = true;

            try {
                $s = IcsoeSeguimiento::query()->whereKey($seguimiento->getKey())->lockForUpdate()->firstOrFail();
                $s->setRelation('proyecto', $seguimiento->relationLoaded('proyecto')
                    ? $seguimiento->proyecto
                    : $s->proyecto);

                $baseNueva = round($this->valorAEjecutar->paraProyecto($s->proyecto), 2);
                $baseAnterior = round((float) $s->monto_base, 2);
                $moAnterior = round((float) $s->mo_estimada_total, 2);

                $resolver = IcsoeSbcAnio::resolver();
                $desglose = $this->calculadora->desglose($s->fecha_inicio, $s->fecha_fin, $resolver);

                $moTotal = round($this->calculadora->moEstimadaTotal(
                    $s->metodo,
                    $baseNueva,
                    (float) $s->porcentaje_mo,
                    $s->superficie_m2 !== null ? (float) $s->superficie_m2 : null,
                    $s->costo_m2 !== null ? (float) $s->costo_m2 : null,
                ), 2);

                $moDiaria = $this->calculadora->moEstimadaDiaria($moTotal, $desglose['total_dias']);

                $this->sincronizarMeses($s, $desglose['meses'], $moDiaria);

                $s->monto_base = $baseNueva;
                $s->mo_estimada_total = $moTotal;
                $s->mo_estimada_diaria = round($moDiaria, 4);
                $s->total_dias = $desglose['total_dias'];
                $s->recalculado_at = now();

                if ($detectarCambio && $baseNueva !== $baseAnterior) {
                    $s->monto_base_anterior = $baseAnterior;
                    $s->mo_estimada_total_anterior = $moAnterior;
                    $s->motivo_cambio = $motivo;
                    $s->estatus = IcsoeEstatus::PendienteVerificacion;
                    $s->verificado_at = null;
                    $s->verificado_por = null;
                }

                $this->aplicarTotales($s);

                return $s;
            } finally {
                self::$recalculando = $anterior;
            }
        });
    }

    /**
     * Recalcula solo los importes derivados de la captura del usuario. No toca
     * el monto base ni marca nada por verificar.
     */
    public function recalcularTotales(IcsoeSeguimiento $seguimiento): IcsoeSeguimiento
    {
        foreach ($seguimiento->meses()->get() as $mes) {
            $mes->mo_real = round($this->calculadora->moReal((float) $mes->dias_cotizados, (float) $mes->sbc_aplicado), 2);
            $mes->save();
        }

        $this->aplicarTotales($seguimiento->refresh());

        return $seguimiento;
    }

    public function verificar(IcsoeSeguimiento $seguimiento, Usuario $usuario): IcsoeSeguimiento
    {
        $seguimiento->forceFill([
            'estatus' => IcsoeEstatus::Vigente,
            'monto_base_anterior' => null,
            'mo_estimada_total_anterior' => null,
            'motivo_cambio' => null,
            'verificado_at' => now(),
            'verificado_por' => $usuario->getKey(),
        ])->save();

        return $seguimiento;
    }

    /**
     * Punto de entrada de los hooks de modelo. Barato de llamar: sale sin
     * consultar nada si el proyecto no tiene seguimiento o si ya hay uno
     * programado en este request.
     */
    public function programarRecalculo(?int $proyectoId, string $motivo): void
    {
        if ($proyectoId === null || self::$recalculando) {
            return;
        }

        if (isset(self::$programados[$proyectoId])) {
            return;
        }

        if (! IcsoeSeguimiento::query()->where('proyecto_id', $proyectoId)->exists()) {
            return;
        }

        self::$programados[$proyectoId] = $motivo;

        // Dentro de una transacción se espera al commit para que el rollup vea
        // el lote completo; fuera de ella corre de inmediato.
        DB::afterCommit(function () use ($proyectoId) {
            $motivoFinal = self::$programados[$proyectoId] ?? null;
            unset(self::$programados[$proyectoId]);

            $seguimiento = IcsoeSeguimiento::query()->where('proyecto_id', $proyectoId)->first();

            if ($seguimiento && $seguimiento->estatus !== IcsoeEstatus::Cerrado) {
                $this->recalcular($seguimiento, $motivoFinal);
            }
        });
    }

    /**
     * Igual que `programarRecalculo`, pero partiendo de una obra: resuelve su
     * proyecto sin hidratar el modelo (los lotes de partidas pasan por aquí).
     */
    public function programarPorObra(?int $obraId, string $motivo): void
    {
        if ($obraId === null || self::$recalculando) {
            return;
        }

        $this->programarRecalculo(
            Obra::query()->whereKey($obraId)->value('proyecto_id'),
            $motivo,
        );
    }

    /** Cierra el seguimiento del proyecto para que deje de pedir verificación. */
    public function cerrarPorProyecto(int $proyectoId): void
    {
        IcsoeSeguimiento::query()
            ->where('proyecto_id', $proyectoId)
            ->update(['estatus' => IcsoeEstatus::Cerrado->value]);
    }

    /** Reabrir el proyecto devuelve su seguimiento a vigente. */
    public function reabrirPorProyecto(int $proyectoId): void
    {
        IcsoeSeguimiento::query()
            ->where('proyecto_id', $proyectoId)
            ->where('estatus', IcsoeEstatus::Cerrado->value)
            ->update(['estatus' => IcsoeEstatus::Vigente->value]);
    }

    /**
     * Fechas propuestas al dar de alta: el periodo que abarcan las obras del
     * proyecto. Son solo un default; el usuario las ajusta al contrato SIROC.
     *
     * @return array{fecha_inicio: string|null, fecha_fin: string|null}
     */
    public function fechasSugeridas(Proyecto $proyecto): array
    {
        $obras = $proyecto->obras()->get(['fecha_inicio', 'fecha_fin']);

        return [
            'fecha_inicio' => $obras->pluck('fecha_inicio')->filter()->min()?->format('Y-m-d'),
            'fecha_fin' => $obras->pluck('fecha_fin')->filter()->max()?->format('Y-m-d'),
        ];
    }

    /**
     * Crea, actualiza o retira los meses del desglose. Los días cotizados y el
     * SBC aplicado nunca se pisan: son captura del usuario.
     *
     * @param  list<array{anio: int, mes: int, dias_proyecto: int, sbc: float}>  $meses
     */
    private function sincronizarMeses(IcsoeSeguimiento $seguimiento, array $meses, float $moDiaria): void
    {
        $existentes = $seguimiento->meses()->get()->keyBy(fn (IcsoeMes $m) => $m->anio.'-'.$m->mes);
        $vigentes = [];

        foreach ($meses as $mes) {
            $clave = $mes['anio'].'-'.$mes['mes'];
            $vigentes[] = $clave;
            $existente = $existentes->get($clave);

            $moEstimada = round($mes['dias_proyecto'] * $moDiaria, 2);

            if ($existente) {
                $existente->fill([
                    'dias_proyecto' => $mes['dias_proyecto'],
                    'sbc' => $mes['sbc'],
                    'mo_estimada' => $moEstimada,
                    'fuera_de_rango' => false,
                ])->save();

                continue;
            }

            $seguimiento->meses()->create([
                'anio' => $mes['anio'],
                'mes' => $mes['mes'],
                'dias_proyecto' => $mes['dias_proyecto'],
                'sbc' => $mes['sbc'],
                'sbc_aplicado' => $mes['sbc'],
                'mo_estimada' => $moEstimada,
                'dias_cotizados' => 0,
                'mo_real' => 0,
            ]);
        }

        foreach ($existentes as $clave => $mes) {
            if (in_array($clave, $vigentes, true)) {
                continue;
            }

            // Se acortó el periodo. Un mes vacío sobra; uno con días capturados
            // se conserva porque esos días sí se cotizaron ante el IMSS.
            if ((float) $mes->dias_cotizados > 0) {
                $mes->fill(['fuera_de_rango' => true, 'dias_proyecto' => 0, 'mo_estimada' => 0])->save();

                continue;
            }

            $mes->delete();
        }
    }

    /** Suma la mano de obra real de los meses y deriva diferencia y riesgo. */
    private function aplicarTotales(IcsoeSeguimiento $seguimiento): void
    {
        $moReal = round((float) $seguimiento->meses()->sum('mo_real'), 2);
        $diferencia = round($this->calculadora->diferenciaMo((float) $seguimiento->mo_estimada_total, $moReal), 2);

        $seguimiento->forceFill([
            'mo_real_total' => $moReal,
            'diferencia_mo' => $diferencia,
            'monto_riesgo' => round($this->calculadora->montoRiesgo($diferencia, (float) $seguimiento->prima_riesgo), 2),
        ])->save();
    }

    /** Método por defecto y prima sugerida a partir del catálogo del año. */
    public function primaSugerida(?int $anio = null): float
    {
        $resolver = IcsoeSbcAnio::resolver();

        if ($resolver->estaVacio()) {
            return 0.0;
        }

        return $resolver->primaRiesgo($anio ?? (int) now()->year);
    }

    public function costoM2Sugerido(?int $anio = null): float
    {
        $resolver = IcsoeSbcAnio::resolver();

        if ($resolver->estaVacio()) {
            return 0.0;
        }

        return $resolver->costoM2($anio ?? (int) now()->year);
    }
}
