<?php

namespace App\Services\Costos;

use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Proveedor;
use Illuminate\Support\Facades\DB;

/**
 * Determina el mejor proveedor de una requisición: aquel que cotizó TODAS las
 * partidas y cuya suma de (cantidad × precio_unitario) es la menor.
 *
 * Reemplaza el cálculo O(n²) en memoria que vivía en Requisicion por una
 * agregación en SQL. Como un proveedor puede tener varias opciones por partida
 * (columnas del comparativo), primero se toma el MÍNIMO precio por partida por
 * proveedor y luego se suma; así las opciones no inflan el total. En empate gana
 * el proveedor de id menor (desempate determinista).
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

        // Mínimo precio por (proveedor, partida): colapsa las opciones a la más
        // barata antes de sumar.
        $minPorPartida = RequisicionCotizacionPrecio::query()
            ->join("{$det} as d", 'd.id', '=', "{$cot}.requisicion_detalle_id")
            ->where('d.requisicion_id', $requisicion->id)
            ->groupBy("{$cot}.proveedor_id", "{$cot}.requisicion_detalle_id")
            ->selectRaw("{$cot}.proveedor_id as proveedor_id, {$cot}.requisicion_detalle_id as detalle_id, MIN({$cot}.precio_unitario) as precio_min");

        $fila = DB::query()
            ->fromSub($minPorPartida, 'm')
            ->join("{$det} as d2", 'd2.id', '=', 'm.detalle_id')
            ->groupBy('m.proveedor_id')
            ->havingRaw('COUNT(DISTINCT m.detalle_id) = ?', [$totalPartidas])
            ->selectRaw('m.proveedor_id as proveedor_id, SUM(m.precio_min * d2.cantidad) as total')
            ->orderBy('total')
            ->orderBy('m.proveedor_id')
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
