<?php

namespace App\Services\Alm;

use App\Models\Alm\Transferencia;
use App\Models\Alm\TransferenciaDetalle;
use Illuminate\Support\Collection;

/**
 * Lo que salió de un almacén y todavía no llegó a otro.
 *
 * **Consulta pura, sin escritura.** No hay columna ni almacén virtual de
 * tránsito: se suma `cantidad_enviada` de las transferencias abiertas, y por eso
 * no puede desincronizarse.
 *
 * Se descartaron las dos alternativas. Una columna `en_transito` en el origen
 * rompe el invariante del ledger —la cantidad bajó y otra columna subió sin
 * movimiento que lo explique— y además haría que el total *sí* cuadrara, que es
 * justo lo que no debe pasar entre las dos firmas. Un almacén virtual ensucia el
 * selector, la visibilidad y el valor del inventario.
 */
class SaldoEnTransito
{
    /** Cuánto de un artículo va en el camino, opcionalmente acotado por par. */
    public function porProducto(int $productoId, ?int $origenId = null, ?int $destinoId = null): float
    {
        return (float) TransferenciaDetalle::query()
            ->where('producto_id', $productoId)
            ->whereHas('transferencia', fn ($q) => $q->enTransito()
                ->when($origenId, fn ($t, int $id) => $t->where('almacen_origen_id', $id))
                ->when($destinoId, fn ($t, int $id) => $t->where('almacen_destino_id', $id)))
            ->sum('cantidad_enviada');
    }

    /**
     * Lo que viene en camino hacia un almacén, por artículo. Es la columna
     * aparte de la pantalla de Existencias: **no** entra al valor del inventario,
     * porque todavía no es de esa bodega.
     *
     * @return array<int, float>
     */
    public function haciaAlmacen(int $almacenId): array
    {
        return TransferenciaDetalle::query()
            ->whereHas('transferencia', fn ($q) => $q->enTransito()->where('almacen_destino_id', $almacenId))
            ->groupBy('producto_id')
            ->select('producto_id')
            ->selectRaw('SUM(cantidad_enviada) as cantidad')
            ->get()
            ->mapWithKeys(fn ($fila): array => [(int) $fila->producto_id => (float) $fila->cantidad])
            ->all();
    }

    /**
     * El desglose por folio, para poder decir *en qué viaje* viene el material.
     *
     * @return Collection<int, Transferencia>
     */
    public function documentosAbiertos(?int $almacenId = null): Collection
    {
        return Transferencia::query()
            ->enTransito()
            ->when($almacenId, fn ($q, int $id) => $q->where(
                fn ($b) => $b->where('almacen_origen_id', $id)->orWhere('almacen_destino_id', $id),
            ))
            ->with(['origen:id,clave', 'destino:id,clave', 'detalles.producto:id,codigo,descripcion,unidad'])
            ->orderBy('fecha_envio')
            ->get();
    }
}
