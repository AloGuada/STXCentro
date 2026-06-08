<?php

namespace App\Services\Costos;

use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Proveedor;

/**
 * Determina el mejor proveedor de una requisición: aquel que cotizó TODAS las
 * partidas y cuya suma de (cantidad × precio_unitario) es la menor.
 *
 * Reemplaza el cálculo O(n²) en memoria que vivía en Requisicion por una
 * agregación en SQL. El índice único (requisicion_detalle_id, proveedor_id)
 * garantiza una sola cotización por partida/proveedor, así que el SUM equivale
 * al antiguo "última cotización por partida gana". En empate gana el proveedor
 * de id menor (desempate determinista).
 */
class BuscadorMejorProveedor
{
    /**
     * @return array{id: int, razon_social: string, nombre_comercial: string|null, total: float}|null
     */
    public function buscar(Requisicion $requisicion): ?array
    {
        $totalPartidas = $requisicion->detalles()->count();
        if ($totalPartidas === 0) {
            return null;
        }

        $cot = (new RequisicionCotizacionPrecio)->getTable();
        $det = (new RequisicionDetalle)->getTable();

        $fila = RequisicionCotizacionPrecio::query()
            ->join("{$det} as d", 'd.id', '=', "{$cot}.requisicion_detalle_id")
            ->where('d.requisicion_id', $requisicion->id)
            ->groupBy("{$cot}.proveedor_id")
            ->havingRaw("COUNT(DISTINCT {$cot}.requisicion_detalle_id) = ?", [$totalPartidas])
            ->selectRaw("{$cot}.proveedor_id as proveedor_id, SUM({$cot}.precio_unitario * d.cantidad) as total")
            ->orderBy('total')
            ->orderBy("{$cot}.proveedor_id")
            ->first();

        if (! $fila) {
            return null;
        }

        $proveedor = Proveedor::find($fila->proveedor_id);

        return [
            'id' => (int) $fila->proveedor_id,
            'razon_social' => $proveedor?->razon_social ?? '',
            'nombre_comercial' => $proveedor?->nombre_comercial,
            'total' => round((float) $fila->total, 2),
        ];
    }
}
