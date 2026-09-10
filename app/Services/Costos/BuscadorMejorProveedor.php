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
 *
 * Los precios se llevan a MXN con el tipo de cambio del documento antes de
 * compararlos y sumarlos: en crudo, una cotización en dólares parecía la más
 * barata y el total mostrado sumaba pesos con dólares como si fueran la misma
 * unidad. Si la requisición no tiene tipo de cambio capturado no hay con qué
 * convertir; el total sale en crudo y `falta_tc` avisa para que la pantalla lo
 * marque.
 */
class BuscadorMejorProveedor
{
    /**
     * Precio unitario expresado en MXN: las divisas se multiplican por el tipo
     * de cambio del documento (1 si no hay, y entonces `falta_tc` avisa).
     */
    private function precioEnMxn(string $cot, string $requisicionAlias): string
    {
        return "{$cot}.precio_unitario * CASE WHEN LOWER(COALESCE({$cot}.moneda, 'mxn')) = 'mxn' THEN 1 ELSE COALESCE(NULLIF({$requisicionAlias}.tipo_cambio, 0), 1) END";
    }

    /**
     * 1 cuando la cotización está en divisa y la requisición no tiene tipo de
     * cambio capturado. `tipo_cambio` nace en 1 por default, así que un 1 sobre
     * una cotización en divisa significa "nadie lo capturó", no una paridad.
     */
    private function faltaTipoCambio(string $cot, string $requisicionAlias): string
    {
        return "CASE WHEN LOWER(COALESCE({$cot}.moneda, 'mxn')) <> 'mxn' AND COALESCE({$requisicionAlias}.tipo_cambio, 0) <= 1 THEN 1 ELSE 0 END";
    }

    /**
     * Versión lote para listados: resuelve el mejor proveedor de N requisiciones
     * en 2 queries (agregado + proveedores) en vez de 2 por requisición.
     *
     * @param  list<int>  $requisicionIds
     * @return array<int, array{id: int, razon_social: string, nombre_comercial: string|null, moneda: string, total: float, falta_tc: bool}|null>
     */
    public function buscarLote(array $requisicionIds): array
    {
        $requisicionIds = array_values(array_unique(array_map('intval', $requisicionIds)));
        if ($requisicionIds === []) {
            return [];
        }

        $cot = (new RequisicionCotizacionPrecio)->getTable();
        $det = (new RequisicionDetalle)->getTable();
        $req = (new Requisicion)->getTable();

        $precio = $this->precioEnMxn($cot, 'r');
        $faltaTc = $this->faltaTipoCambio($cot, 'r');

        $minPorPartida = RequisicionCotizacionPrecio::query()
            ->join("{$det} as d", 'd.id', '=', "{$cot}.requisicion_detalle_id")
            ->join("{$req} as r", 'r.id', '=', 'd.requisicion_id')
            ->whereIn('d.requisicion_id', $requisicionIds)
            ->where('d.solo_cotizacion', false)
            ->groupBy('d.requisicion_id', "{$cot}.proveedor_id", "{$cot}.requisicion_detalle_id")
            ->selectRaw("d.requisicion_id as requisicion_id, {$cot}.proveedor_id as proveedor_id, {$cot}.requisicion_detalle_id as detalle_id, MIN({$precio}) as precio_min, MAX({$faltaTc}) as falta_tc");

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
            ->selectRaw('m.requisicion_id as requisicion_id, m.proveedor_id as proveedor_id, SUM(m.precio_min * d2.cantidad) as total, MAX(m.falta_tc) as falta_tc')
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
                'moneda' => 'mxn',
                'total' => round((float) $fila->total, 2),
                'falta_tc' => (bool) $fila->falta_tc,
            ] : null;
        }

        return $resultado;
    }

    /**
     * @return array{id: int, razon_social: string, nombre_comercial: string|null, moneda: string, total: float, falta_tc: bool}|null
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
        $req = (new Requisicion)->getTable();

        $precio = $this->precioEnMxn($cot, 'r');
        $faltaTc = $this->faltaTipoCambio($cot, 'r');

        // Mínimo precio por (proveedor, partida): colapsa las opciones a la más
        // barata antes de sumar. El mínimo se toma sobre el precio ya en MXN,
        // porque una opción en dólares no se compara contra una en pesos.
        $minPorPartida = RequisicionCotizacionPrecio::query()
            ->join("{$det} as d", 'd.id', '=', "{$cot}.requisicion_detalle_id")
            ->join("{$req} as r", 'r.id', '=', 'd.requisicion_id')
            ->where('d.requisicion_id', $requisicion->id)
            ->where('d.solo_cotizacion', false)
            ->groupBy("{$cot}.proveedor_id", "{$cot}.requisicion_detalle_id")
            ->selectRaw("{$cot}.proveedor_id as proveedor_id, {$cot}.requisicion_detalle_id as detalle_id, MIN({$precio}) as precio_min, MAX({$faltaTc}) as falta_tc");

        $fila = DB::query()
            ->fromSub($minPorPartida, 'm')
            ->join("{$det} as d2", 'd2.id', '=', 'm.detalle_id')
            ->groupBy('m.proveedor_id')
            ->havingRaw('COUNT(DISTINCT m.detalle_id) = ?', [$totalPartidas])
            ->selectRaw('m.proveedor_id as proveedor_id, SUM(m.precio_min * d2.cantidad) as total, MAX(m.falta_tc) as falta_tc')
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
            'moneda' => 'mxn',
            'total' => round((float) $fila->total, 2),
            'falta_tc' => (bool) $fila->falta_tc,
        ];
    }
}
