<?php

use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Existencia;
use App\Models\Costos\Producto;

/**
 * La migración de datos que le da a Almacén su renglón propio por cada producto
 * que ya guarda. Se invoca su `up()` a mano: sobre la base de pruebas ya corrió
 * con el catálogo vacío, así que llamarla otra vez con datos es lo que hay que
 * probar — y de paso demuestra que es recorrible.
 */
function poblarArticulos(): void
{
    $migracion = require database_path('migrations/2026_09_02_200100_poblar_alm_articulos_desde_productos.php');

    $migracion->up();
}

describe('poblar alm_articulos', function () {
    test('un producto con existencia se convierte en artículo ligado', function () {
        $producto = Producto::factory()->create(['descripcion' => 'PRIMARIO GRIS', 'unidad' => 'LTS']);
        Existencia::factory()->create([
            'almacen_id' => Almacen::factory(),
            'producto_id' => $producto->id,
        ]);

        poblarArticulos();

        $articulo = Articulo::where('producto_id', $producto->id)->first();

        expect($articulo)->not->toBeNull()
            ->and($articulo->descripcion)->toBe('PRIMARIO GRIS')
            ->and($articulo->unidad)->toBe('LTS')
            ->and($articulo->codigo)->toBe($producto->codigo);
    });

    test('un producto que Almacén nunca ha tocado no se convierte', function () {
        // El caso de los servicios, los fletes y el catálogo de Compras que
        // nunca pisó una bodega: sin rastro en las siete tablas, sin artículo.
        $producto = Producto::factory()->create();

        poblarArticulos();

        expect(Articulo::where('producto_id', $producto->id)->exists())->toBeFalse();
    });

    test('cuenta lo que hay, no lo que podría haber', function () {
        $conMaterial = Producto::factory()->count(3)->create();
        Producto::factory()->count(7)->create();

        foreach ($conMaterial as $producto) {
            Existencia::factory()->create([
                'almacen_id' => Almacen::factory(),
                'producto_id' => $producto->id,
            ]);
        }

        poblarArticulos();

        expect(Articulo::count())->toBe(3);
    });

    test('correrla dos veces no duplica ni truena', function () {
        // Los layouts entran por tandas y hay que poder recorrerla.
        $producto = Producto::factory()->create();
        Existencia::factory()->create([
            'almacen_id' => Almacen::factory(),
            'producto_id' => $producto->id,
        ]);

        poblarArticulos();
        poblarArticulos();

        expect(Articulo::where('producto_id', $producto->id)->count())->toBe(1);
    });

    test('el artículo se lleva lo de Almacén y deja lo de Compras', function () {
        $area = \App\Models\Alm\Area::factory()->create();
        $producto = Producto::factory()->create([
            'area_id' => $area->id,
            'clasificacion_abc' => 'A',
            'stock_minimo' => 500,
            'codigo_barras' => '7501234567890',
            'requiere_verificacion' => true,
        ]);
        Existencia::factory()->create([
            'almacen_id' => Almacen::factory(),
            'producto_id' => $producto->id,
        ]);

        poblarArticulos();

        $articulo = Articulo::where('producto_id', $producto->id)->firstOrFail();

        expect($articulo->area_id)->toBe($area->id)
            ->and($articulo->clasificacion_abc->value)->toBe('A')
            ->and((float) $articulo->stock_minimo)->toBe(500.0)
            ->and($articulo->codigo_barras)->toBe('7501234567890')
            ->and($articulo->requiere_verificacion)->toBeTrue();

        // Y Compras se queda como estaba: la migración no le quita nada.
        expect($producto->fresh()->area_id)->toBe($area->id);
    });
});
