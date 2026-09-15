<?php

namespace App\Models\Concerns;

use App\Models\Alm\Articulo;
use App\Models\Costos\Producto;
use App\Models\Item;
use App\Services\Alm\ResolvedorArticulo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mientras `item_id` conviva con `producto_id` y `articulo_id` en las tablas
 * hijas del catálogo, con cualquiera de las tres que traiga el renglón se
 * llenan las otras.
 *
 * Va como hook del modelo y no parchando cada escritura: el riesgo no son los
 * lugares que hoy insertan en estas tablas, es el que alguien escriba la
 * semana entrante sin saber que había tres columnas que llenar. Un renglón
 * así saldría con `item_id` nulo y la fase 4 no podría cerrarse.
 *
 * No decide nada, no puede correr de más y es idempotente. **Se borra en la
 * fase 4**, junto con las llaves viejas. Es andamio, no diseño.
 */
trait LlenaLlavesDeItem
{
    /**
     * Qué llaves viejas tiene la tabla de este modelo.
     *
     * @return list<string>
     */
    protected static function llavesLegado(): array
    {
        return ['producto_id', 'articulo_id'];
    }

    protected static function bootLlenaLlavesDeItem(): void
    {
        static::saving(function (Model $modelo): void {
            $llaves = static::llavesLegado();
            $conArticulo = in_array('articulo_id', $llaves, true);
            $conProducto = in_array('producto_id', $llaves, true);

            if ($modelo->item_id === null) {
                if ($conArticulo && $modelo->articulo_id !== null) {
                    $modelo->item_id = Articulo::query()->whereKey($modelo->articulo_id)->value('item_id');
                } elseif ($conProducto && $modelo->producto_id !== null) {
                    $modelo->item_id = Producto::query()->whereKey($modelo->producto_id)->value('item_id');
                }
            }

            if ($modelo->item_id === null) {
                return;
            }

            if ($conArticulo && $modelo->articulo_id === null) {
                // Si el item todavía no tiene artículo (un producto que nunca
                // pisó bodega), el resolvedor lo crea: llegar aquí es mover
                // kardex, y tener artículo es llevarlo.
                $modelo->articulo_id = Articulo::query()->where('item_id', $modelo->item_id)->value('id')
                    ?? ($conProducto && $modelo->producto_id !== null
                        ? app(ResolvedorArticulo::class)->paraProducto((int) $modelo->producto_id)
                        : null);
            }

            if ($conProducto && $modelo->producto_id === null) {
                $modelo->producto_id = Producto::query()->where('item_id', $modelo->item_id)->value('id');
            }
        });
    }

    /**
     * La identidad del insumo en el catálogo maestro.
     *
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
