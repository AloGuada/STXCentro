<?php

namespace App\Services\Qal;

use App\Models\Obra as ObraDelPortal;
use App\Models\Qal\Inspeccion;
use Illuminate\Support\Collection;

/**
 * Las cuentas de la inspección visual, en un solo lugar.
 *
 * Viven aquí y no en el controlador porque las consumen el reporte semanal y,
 * después, el tablero: la definición no puede estar escrita dos veces.
 *
 * La que viene discutida del formato en Excel y no hay que perder: el
 * porcentaje de incidencias es **piezas liberadas que traían rechazo previo ÷
 * piezas liberadas**. Cada pieza cuenta una vez, en la semana en que se liberó,
 * arrastrando toda su historia; los rechazos pueden ser de semanas anteriores.
 * No es «rechazadas ÷ liberadas de la misma semana»: eso daba 140 % y 240 %,
 * porque son dos conjuntos distintos de piezas. Es un FPY al revés, así que
 * nunca pasa de 100.
 *
 * La pieza es su QR y la puerta de cada transformación es la del avance de
 * producción: en 2ª se libera en soldado, en pintura cuando pintura la libera.
 */
class EstadisticaInspecciones
{
    public function __construct(private readonly HistorialDePiezas $historial) {}

    /**
     * Las hojas 0, 1 y 3 del reporte semanal.
     *
     * @return array{visual: list<array<string, mixed>>, kg_liberados: float, serie: list<array{semana: int, t2: float|null, t3: float|null}>}
     */
    public function hojasDelReporteSemanal(int $anio, int $semana): array
    {
        $obras = Inspeccion::query()
            ->where('anio', $anio)
            ->whereNotNull('qr')
            ->distinct()
            ->pluck('obra_id')
            ->map(fn ($obra): int => (int) $obra)
            ->all();

        if ($obras === []) {
            return ['visual' => [], 'kg_liberados' => 0.0, 'serie' => []];
        }

        $segunda = collect($this->historial->piezas($obras, '2'));
        $pintura = collect($this->historial->piezas($obras, '3'));
        $corte = $this->clave($anio, $semana);

        return [
            'visual' => $this->visual($segunda, $pintura, $corte),
            // Estructura principal: lo que se liberó en 2ª esa semana.
            'kg_liberados' => round((float) $this->liberadasEn($segunda, $corte)->sum('kgLiberada'), 3),
            'serie' => $this->serie($segunda, $pintura, $anio, $semana),
        ];
    }

    /**
     * Hoja 1: obra por obra, lo liberado en la semana y cuánto de eso venía de
     * un rechazo. Sólo salen las obras que liberaron algo.
     *
     * @param  Collection<int, array<string, mixed>>  $segunda
     * @param  Collection<int, array<string, mixed>>  $pintura
     * @return list<array<string, mixed>>
     */
    private function visual(Collection $segunda, Collection $pintura, string $corte): array
    {
        $liberadas2t = $this->liberadasEn($segunda, $corte)->groupBy('obra_id');
        $liberadasPintura = $this->liberadasEn($pintura, $corte)->groupBy('obra_id');
        $ids = $liberadas2t->keys()->merge($liberadasPintura->keys())->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $nombres = ObraDelPortal::query()->whereIn('id', $ids)->pluck('no', 'id');

        return $ids
            ->map(function (int $obraId) use ($liberadas2t, $liberadasPintura, $nombres): array {
                $dos = $liberadas2t->get($obraId, collect());
                $tres = $liberadasPintura->get($obraId, collect());

                return [
                    'obra_id' => $obraId,
                    'obra' => (string) ($nombres[$obraId] ?? $obraId),
                    'liberadas2t' => $dos->count(),
                    'conRechazo2t' => $dos->where('conRechazoPrevio', true)->count(),
                    'liberadasPintura' => $tres->count(),
                    'conRechazoPintura' => $tres->where('conRechazoPrevio', true)->count(),
                ];
            })
            ->sortBy('obra', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Hoja 3: la misma cuenta semana a semana hasta el corte, para ver si esta
     * semana es un pico o la tónica. Sólo las semanas que liberaron algo; una
     * transformación sin liberadas esa semana llega en nulo, no en cero.
     *
     * @param  Collection<int, array<string, mixed>>  $segunda
     * @param  Collection<int, array<string, mixed>>  $pintura
     * @return list<array{semana: int, t2: float|null, t3: float|null}>
     */
    private function serie(Collection $segunda, Collection $pintura, int $anio, int $semana): array
    {
        $serie = [];

        for ($numero = 1; $numero <= $semana; $numero++) {
            $clave = $this->clave($anio, $numero);
            $dos = $this->liberadasEn($segunda, $clave);
            $tres = $this->liberadasEn($pintura, $clave);

            if ($dos->isEmpty() && $tres->isEmpty()) {
                continue;
            }

            $serie[] = [
                'semana' => $numero,
                't2' => $this->porcentaje($dos->where('conRechazoPrevio', true)->count(), $dos->count()),
                't3' => $this->porcentaje($tres->where('conRechazoPrevio', true)->count(), $tres->count()),
            ];
        }

        return $serie;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $piezas
     * @return Collection<int, array<string, mixed>>
     */
    private function liberadasEn(Collection $piezas, string $clave): Collection
    {
        return $piezas->where('semanaLiberada', $clave);
    }

    private function clave(int $anio, int $semana): string
    {
        return sprintf('%04d-S%02d', $anio, $semana);
    }

    /** Con un decimal, o nulo cuando no se liberó nada. */
    private function porcentaje(int $parte, int $total): ?float
    {
        return $total > 0 ? round($parte * 100 / $total, 1) : null;
    }
}
