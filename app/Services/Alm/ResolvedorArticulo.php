<?php

namespace App\Services\Alm;

use App\Models\Alm\Articulo;
use App\Models\Costos\Producto;

/**
 * El artículo con el que Almacén guarda un producto de Compras, creándolo si es
 * la primera vez que ese producto pisa una bodega.
 *
 * Se busca por el item, no por `producto_id`: el artículo que abrió la carga
 * inicial de un almacén nació sin producto, y cuando Compras compra ese mismo
 * insumo el maestro ya los tiene bajo la misma identidad. Antes esa búsqueda
 * fallaba y se creaba un segundo artículo; ésa era la fábrica de duplicados.
 * Si el artículo existe y todavía no apunta al producto, aquí se le pone.
 *
 * Crear al vuelo es correcto y no es una concesión: se invoca cuando algo *ya*
 * está moviendo kardex, y tener renglón en `alm_articulos` es justamente llevar
 * kardex. Es el único lugar donde se copian al artículo los datos que Almacén
 * administra (área, tipo, ABC...) desde el producto; código, descripción y
 * unidad los pone el maestro.
 */
class ResolvedorArticulo
{
    /**
     * Caché por corrida. Una recepción de 40 renglones del mismo producto
     * preguntaría 40 veces lo mismo, y el import de un layout, cientos.
     *
     * @var array<int, int>
     */
    private array $resueltos = [];

    /** El id del artículo de ese producto; lo crea si todavía no existe. */
    public function paraProducto(int $productoId): ?int
    {
        if (isset($this->resueltos[$productoId])) {
            return $this->resueltos[$productoId];
        }

        $producto = Producto::query()->find($productoId);

        if ($producto === null) {
            return null;
        }

        $articulo = Articulo::query()->where('item_id', $producto->item_id)->first();

        if ($articulo !== null && $articulo->producto_id === null) {
            $articulo->forceFill(['producto_id' => $producto->id])->saveQuietly();
        }

        $articuloId = $articulo?->id ?? $this->crearDesde($producto)->id;

        $this->resueltos[$productoId] = $articuloId;

        return $articuloId;
    }

    private function crearDesde(Producto $producto): Articulo
    {
        return Articulo::create([
            'item_id' => $producto->item_id,
            'producto_id' => $producto->id,
            'codigo' => $producto->codigo,
            'codigo_barras' => $producto->codigo_barras ?? $producto->codigo,
            'descripcion' => $producto->descripcion,
            'unidad' => $producto->unidad,
            'idsteelex' => $producto->idsteelex,
            'area_id' => $producto->area_id,
            'tipo' => $producto->tipo,
            'se_controla_por_pieza' => $producto->se_controla_por_pieza,
            'requiere_verificacion' => $producto->requiere_verificacion,
            'stock_minimo' => $producto->stock_minimo,
            'clasificacion_abc' => $producto->clasificacion_abc,
            'imagen' => $producto->imagen,
            'activo' => $producto->activo,
            'creado_por' => $producto->creado_por,
        ]);
    }
}
