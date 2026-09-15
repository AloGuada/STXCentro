<?php

namespace App\Services\Costos;

use App\Enums\Costos\OrdenCompraEstatus;
use App\Enums\Costos\RequisicionEstatus;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Producto;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Item;
use App\Services\Catalogo\CatalogoMaestro;
use Illuminate\Support\Collection;

/**
 * Pone producto a las partidas que nacieron sin él.
 *
 * Hasta septiembre de 2026 la requisición aceptaba partidas tecleadas sin
 * catálogo; sus órdenes y recepciones heredaron el hueco y esos renglones
 * nunca llegaron al kardex. Aquí se empareja cada partida con el item activo
 * que se llama igual (misma regla que el combobox: sin acentos ni dobles
 * espacios) y se escribe el producto en la partida de la requisición, la de
 * la orden y los renglones de recepción que cuelgan de ella.
 *
 * Lo que no casa por nombre se lista y no se toca: un flete («ENVIO») se queda
 * sin producto a propósito, y lo demás es un alta en Almacén > Artículos y
 * volver a correr.
 */
class AsignadorProductoAPartidas
{
    public function __construct(private readonly CatalogoMaestro $catalogo) {}

    /**
     * @return array{
     *   ordenes: Collection<int, array{detalle: OrdenCompraDetalle, item: Item, producto: Producto}>,
     *   requisiciones: Collection<int, array{detalle: RequisicionDetalle, item: Item, producto: Producto}>,
     *   sin_producto: Collection<int, array{origen: string, folio: string, descripcion: string}>
     * }
     */
    public function planear(): array
    {
        $ordenes = collect();
        $requisiciones = collect();
        $sinProducto = collect();

        $partidasOc = OrdenCompraDetalle::query()
            ->whereNull('producto_id')
            ->whereHas('ordenCompra', fn ($q) => $q->where('estatus', '<>', OrdenCompraEstatus::Cancelada->value))
            ->with('ordenCompra:id,folio')
            ->orderBy('id')
            ->get();

        foreach ($partidasOc as $partida) {
            $item = $this->itemLlamado((string) $partida->descripcion);

            if ($item === null) {
                $sinProducto->push(['origen' => 'OC', 'folio' => (string) $partida->ordenCompra?->folio, 'descripcion' => (string) $partida->descripcion]);

                continue;
            }

            $ordenes->push(['detalle' => $partida, 'item' => $item, 'producto' => $item->producto]);
        }

        $partidasReq = RequisicionDetalle::query()
            ->whereNull('producto_id')
            ->whereHas('requisicion', fn ($q) => $q->whereNotIn('estatus', [RequisicionEstatus::Cancelada->value, RequisicionEstatus::Rechazada->value]))
            ->with('requisicion:id,folio')
            ->orderBy('id')
            ->get();

        foreach ($partidasReq as $partida) {
            $item = $this->itemLlamado((string) $partida->descripcion);

            if ($item === null) {
                $sinProducto->push(['origen' => 'REQ', 'folio' => (string) $partida->requisicion?->folio, 'descripcion' => (string) $partida->descripcion]);

                continue;
            }

            $requisiciones->push(['detalle' => $partida, 'item' => $item, 'producto' => $item->producto]);
        }

        return [
            'ordenes' => $ordenes,
            'requisiciones' => $requisiciones,
            'sin_producto' => $sinProducto->unique(fn (array $fila): string => $fila['origen'].$fila['folio'].mb_strtolower($fila['descripcion']))->values(),
        ];
    }

    /**
     * @param  array{ordenes: Collection<int, array{detalle: OrdenCompraDetalle, item: Item, producto: Producto}>, requisiciones: Collection<int, array{detalle: RequisicionDetalle, item: Item, producto: Producto}>}  $plan
     * @return array{ordenes: int, requisiciones: int, recepciones: int}
     */
    public function ejecutar(array $plan): array
    {
        $resumen = ['ordenes' => 0, 'requisiciones' => 0, 'recepciones' => 0];

        foreach ($plan['ordenes'] as ['detalle' => $partida, 'item' => $item, 'producto' => $producto]) {
            $partida->forceFill($this->valoresPara($partida, $item))->saveQuietly();
            $resumen['ordenes']++;

            $partidaReq = $partida->requisicionDetalle;

            if ($partidaReq !== null && $partidaReq->producto_id === null) {
                $partidaReq->forceFill($this->valoresPara($partidaReq, $item))->saveQuietly();
                $resumen['requisiciones']++;
            }

            $resumen['recepciones'] += EntregaDetalle::query()
                ->where('orden_compra_detalle_id', $partida->id)
                ->whereNull('producto_id')
                ->update(['producto_id' => $producto->id]);
        }

        foreach ($plan['requisiciones'] as ['detalle' => $partida, 'item' => $item]) {
            $partida->refresh();

            if ($partida->producto_id !== null) {
                continue;
            }

            $partida->forceFill($this->valoresPara($partida, $item))->saveQuietly();
            $resumen['requisiciones']++;
        }

        return $resumen;
    }

    /** El item activo que se llama así y que tiene su cara de Compras activa. */
    private function itemLlamado(string $descripcion): ?Item
    {
        $item = $this->catalogo->buscar($descripcion);

        if ($item === null || ! $item->producto()->where('activo', true)->exists()) {
            return null;
        }

        return $item->load('producto');
    }

    /**
     * El producto y, si la partida no traía código, el del maestro (el del
     * item, no la copia del producto: hay productos adoptados por la fusión que
     * todavía traen su código viejo). La descripción y la unidad son snapshot
     * del documento y no se tocan.
     *
     * @return array<string, mixed>
     */
    private function valoresPara(OrdenCompraDetalle|RequisicionDetalle $partida, Item $item): array
    {
        $valores = ['producto_id' => $item->producto->id];

        if (blank($partida->codigo_producto) && $item->codigo !== null) {
            $valores['codigo_producto'] = $item->codigo;
        }

        return $valores;
    }
}
