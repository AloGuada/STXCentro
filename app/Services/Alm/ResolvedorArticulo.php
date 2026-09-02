<?php

namespace App\Services\Alm;

use App\Models\Alm\Articulo;
use App\Models\Costos\Producto;

/**
 * El artículo con el que Almacén guarda un producto de Compras, creándolo si es
 * la primera vez que ese producto pisa una bodega.
 *
 * Crear al vuelo es correcto y no es una concesión: se invoca cuando algo *ya*
 * está moviendo kardex, y tener renglón en `alm_articulos` es justamente llevar
 * kardex. El artículo nace **ligado**, porque el producto viene conocido —de una
 * orden de compra, de un ajuste, de lo que sea que provocó el movimiento—, así
 * que por esta vía nunca aparece uno suelto. Los sueltos salen de la carga
 * inicial de un almacén, que es otro camino.
 *
 * Es el único lugar donde se copian los datos del producto al artículo. A
 * partir de ahí cada tabla manda sobre lo suyo: Compras edita descripción y
 * unidad para cotizar, Almacén edita área, ABC y ubicación, y que difieran es
 * información, no desincronización.
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

        $articuloId = Articulo::query()->where('producto_id', $productoId)->value('id');

        if ($articuloId === null) {
            $articuloId = $this->crearDesde($productoId)?->id;
        }

        if ($articuloId !== null) {
            $this->resueltos[$productoId] = $articuloId;
        }

        return $articuloId;
    }

    /**
     * Null cuando el producto no existe: no es asunto de este servicio
     * inventarlo, y la llave foránea del renglón que lo pidió va a quejarse
     * mucho más claro que un artículo huérfano.
     */
    private function crearDesde(int $productoId): ?Articulo
    {
        $producto = Producto::find($productoId);

        if ($producto === null) {
            return null;
        }

        return Articulo::create([
            'producto_id' => $producto->id,
            'codigo' => $producto->codigo,
            'codigo_barras' => $producto->codigo_barras,
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
