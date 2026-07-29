<?php

namespace App\Services\Prod;

use App\Models\Prod\Destajo;
use App\Models\Prod\Registro;
use Illuminate\Support\Collection;

/**
 * Piezas que se empezaron a pagar en parcialidades y todavía tienen saldo.
 *
 * No incluye las piezas que nadie ha tocado (esas se capturan normal): sólo lo
 * que quedó a medias, para poder liquidarlo en el destajo siguiente al mismo
 * grupo que lo trabajó.
 */
class PendientesDeLiquidar
{
    public function __construct(private AvanceDePiezas $avance) {}

    /**
     * @return Collection<int, array{
     *     concepto_id: int,
     *     marca: string,
     *     descripcion: string,
     *     obra: string,
     *     grupo_trabajo_id: int|null,
     *     grupo_trabajo: string|null,
     *     cantidad_catalogo: int,
     *     pagado: float,
     *     saldo: float,
     *     cantidad_sugerida: int,
     *     porcentaje_sugerido: float
     * }>
     */
    public function paraDestajo(Destajo $destajo): Collection
    {
        $parciales = Registro::query()
            ->with(['concepto.obra', 'grupoTrabajo'])
            ->where('porcentaje', '<', 100)
            ->where('fecha', '<', $destajo->fecha_inicio)
            ->orderByDesc('fecha')
            ->get()
            ->filter(fn (Registro $registro) => $registro->concepto !== null);

        if ($parciales->isEmpty()) {
            return collect();
        }

        $capturadoPorObra = $parciales
            ->pluck('concepto.obra_id')
            ->unique()
            ->mapWithKeys(fn (int $obraId) => [$obraId => $this->avance->mapaDeObra($obraId)]);

        return $parciales
            // Una fila por pieza: el último parcial manda para sugerir grupo y cantidad.
            ->unique(fn (Registro $registro) => $registro->concepto->obra_id.'|'.$registro->concepto->marca)
            ->map(function (Registro $registro) use ($capturadoPorObra) {
                $concepto = $registro->concepto;

                $pagado = $capturadoPorObra->get($concepto->obra_id)?->capturadoDe($concepto) ?? 0.0;
                $saldo = round(max(0, (float) $concepto->cantidad - $pagado), 4);

                if ($saldo <= 0) {
                    return null;
                }

                // Se sugiere cerrar el mismo lote: las piezas del último parcial
                // al porcentaje que les falta para llegar al 100%. El saldo del
                // catálogo sólo actúa como techo — si la pieza todavía tiene
                // mucho pendiente, no debe inflar la sugerencia a 100%.
                $cantidadSugerida = min($registro->cantidad, (int) $concepto->cantidad);
                $faltaDelLote = round(100 - (float) $registro->porcentaje, 2);
                $topeDelSaldo = $cantidadSugerida > 0 ? $saldo / $cantidadSugerida * 100 : 0.0;

                $porcentajeSugerido = $cantidadSugerida > 0
                    ? round(max(0, min($faltaDelLote, $topeDelSaldo)), 2)
                    : 0.0;

                return [
                    'concepto_id' => $concepto->id,
                    'marca' => $concepto->marca,
                    'descripcion' => $concepto->descripcion,
                    'obra' => $concepto->obra?->no ?? '',
                    'grupo_trabajo_id' => $registro->grupo_trabajo_id,
                    'grupo_trabajo' => $registro->grupoTrabajo?->descripcion,
                    'cantidad_catalogo' => (int) $concepto->cantidad,
                    'pagado' => $pagado,
                    'saldo' => $saldo,
                    'cantidad_sugerida' => $cantidadSugerida,
                    'porcentaje_sugerido' => $porcentajeSugerido,
                ];
            })
            ->filter()
            ->sortBy('marca')
            ->values();
    }
}
