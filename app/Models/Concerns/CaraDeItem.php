<?php

namespace App\Models\Concerns;

use App\Exceptions\Catalogo\ItemDuplicadoException;
use App\Models\Item;
use App\Services\Catalogo\CatalogoMaestro;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que comparten las dos caras del catálogo maestro: el producto de Compras
 * y el artículo de Almacén.
 *
 * Una cara no nace sin item. Si al crearla no trae `item_id`, se resuelve
 * aquí: se busca un activo que se llame igual y, si no lo hay, se crea. Es lo
 * que vuelve imposible el duplicado por descripción sin que cada alta tenga
 * que acordarse de preguntar. Y si el que se llama igual ya tiene esta misma
 * cara, se rechaza con nombre y código: esa es la señal de "usa ese".
 *
 * Código, descripción y unidad que la cara guarda son copias de lectura del
 * item; cuando se editan en la cara suben al maestro y de ahí bajan a la otra.
 */
trait CaraDeItem
{
    abstract protected static function nombreDeCara(): string;

    protected static function bootCaraDeItem(): void
    {
        static::creating(function (self $cara): void {
            if ($cara->item_id === null) {
                $cara->item_id = $cara->resolverItemAlNacer()->id;
            }
        });

        static::updated(function (self $cara): void {
            $cambios = array_intersect_key($cara->getChanges(), array_flip(['codigo', 'descripcion', 'unidad']));

            if ($cambios === []) {
                return;
            }

            $cara->item()->first()?->update($cambios);
        });
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * El item de esta cara cuando nace sin él: el activo que se llama igual, o
     * uno nuevo. Al reutilizar, la cara adopta código y unidad del maestro si
     * venía sin ellos; lo que la cara traiga distinto se respeta en la cara y
     * no se sube, porque el maestro ya lo decidió.
     */
    protected function resolverItemAlNacer(): Item
    {
        $maestro = app(CatalogoMaestro::class);
        $item = $maestro->buscar((string) $this->descripcion);

        if ($item === null) {
            return Item::create([
                'codigo' => $this->codigo,
                'descripcion' => trim((string) $this->descripcion),
                'unidad' => $this->unidad,
                'activo' => true,
                'creado_por' => $this->creado_por,
            ]);
        }

        if ($item->{static::nombreDeCara()}()->exists()) {
            throw new ItemDuplicadoException($item, static::etiquetaDeCara());
        }

        if ($this->codigo === null && $item->codigo !== null) {
            $this->codigo = $item->codigo;
        } elseif ($item->codigo === null && $this->codigo !== null) {
            $item->update(['codigo' => $this->codigo]);
        }

        $this->descripcion = $item->descripcion;
        $this->unidad = $item->unidad;

        return $item;
    }

    protected static function etiquetaDeCara(): string
    {
        return static::nombreDeCara() === 'producto' ? 'el producto' : 'el artículo';
    }
}
