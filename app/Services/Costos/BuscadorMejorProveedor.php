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
     * Versión lote para listados: resuelve el mejor proveedor de N requisiciones
     * en 2 queries (agregado + proveedores) en vez de 2 por requisición.
     *
     * @param  list<int>  $requisicionIds
     * @return array<int, array{id: int, razon_social: string, nombre_comercial: string|null, total: float}|null>
     */
    public function buscarLote(array $requisicionIds): array
    {
        $requisicionIds = array_values(array_unique(array_map('intval', $requisicionIds)));
        if ($requisicionIds === []) {
            return [];
        }

        $cot = (new RequisicionCotizacionPrecio)->getTable();
        $det = (new RequisicionDetalle)->getTable();

        $minPorPartida = RequisicionCotizacionPrecio::query()
            ->join("{$det} as d", 'd.id', '=', "{$cot}.requisicion_detalle_id")
            ->whereIn('d.requisicion_id', $requisicionIds)
            ->where('d.solo_cotizacion', false)
            ->groupBy('d.requisicion_id', "{$cot}.proveedor_id", "{$cot}.requisicion_detalle_id")
            ->selectRaw("d.requisicion_id as requisicion_id, {$cot}.proveedor_id as proveedor_id, {$cot}.requisicion_detalle_id as detalle_id, MIN({$cot}.precio_unitario) as precio_min");

        $totalPartidas = RequisicionDetalle::query()
            ->whereIn('requisicion_id', $requisicionIds)
            ->where('solo_cotizacion', false)
            ->groupBy('requisicion_id')
            ->selectRaw('requisicion_id, COUNT(*) as total_partidas');

        $filas = DB::query()
            ->fromSub($minPorPartida, 'm')
            ->join("{$det} as d2", 'd2.id', '=', 'm.detalle_id')
            ->joinSub($totalPartidas, 'tp', 'tp.requisicion_id', '=', 'm.requisicion_id')
            ->groupBy('m.requisicion_id', 'm.proveedor_id')
            ->havingRaw('COUNT(DISTINCT m.detalle_id) = MAX(tp.total_partidas)')
            ->selectRaw('m.requisicion_id as requisicion_id, m.proveedor_id as proveedor_id, SUM(m.precio_min * d2.cantidad) as total')
            ->orderBy('total')
            ->orderBy('m.proveedor_id')
            ->get();

        $mejores = [];
        foreach ($filas as $fila) {
            $mejores[(int) $fila->requisicion_id] ??= $fila;
        }

        $proveedores = Proveedor::query()
            ->whereIn('id', array_map(fn ($f) => (int) $f->proveedor_id, $mejores))
            ->get(['id', 'razon_social', 'nombre_comercial'])
            ->keyBy('id');

        $resultado = [];
        foreach ($requisicionIds as $id) {
            $fila = $mejores[$id] ?? null;
            $proveedor = $fila ? $proveedores->get((int) $fila->proveedor_id) : null;
            $resultado[$id] = $fila ? [
                'id' => (int) $fila->proveedor_id,
                'razon_social' => $proveedor?->razon_social ?? '',
                'nombre_comercial' => $proveedor?->nombre_comercial,
                'total' => round((float) $fila->total, 2),
            ] : null;
        }

        return $resultado;
    }

    /**
     * @return array{id: int, razon_social: string, nombre_comercial: string|null, total: float}|null
     */
    public function buscar(Requisicion $requisicion): ?array
    {
        // Las partidas "solo cotización" son de referencia y no se adjudican:
        // no cuentan para el total ni obligan al proveedor a haberlas cotizado.
        $totalPartidas = $requisicion->detalles()->where('solo_cotizacion', false)->count();
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
            ->where('d.solo_cotizacion', false)
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
