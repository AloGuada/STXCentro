<?php

use App\Exceptions\Catalogo\ItemDuplicadoException;
use App\Models\Alm\Articulo;
use App\Models\Costos\Producto;
use Illuminate\Database\QueryException;

describe('el catálogo propio de Almacén', function () {
    test('un artículo puede existir sin producto de Compras', function () {
        // Es el material que abre un almacén: existe en la bodega y todavía no
        // se empareja con nada del catálogo de Compras.
        $articulo = Articulo::factory()->sinLigar()->create(['descripcion' => 'PRIMARIO GRIS']);

        expect($articulo->producto_id)->toBeNull()
            ->and($articulo->producto)->toBeNull()
            ->and(Articulo::sinLigar()->count())->toBe(1);
    });

    test('ligarlo y desligarlo es escribir una columna', function () {
        $articulo = Articulo::factory()->sinLigar()->create();
        $producto = Producto::factory()->create();

        $articulo->update(['producto_id' => $producto->id]);

        expect($articulo->fresh()->producto->is($producto))->toBeTrue()
            ->and($producto->fresh()->articulo->is($articulo))->toBeTrue();

        // Y se puede deshacer, que es de lo que se trataba.
        $articulo->update(['producto_id' => null]);

        expect($articulo->fresh()->producto)->toBeNull();
    });

    test('dos artículos no pueden apuntar al mismo producto', function () {
        // Partirían la existencia de un mismo insumo en dos renglones y el
        // kardex dejaría de cuadrar contra Compras. Lo detiene el maestro
        // antes que la base: el item del producto ya tiene su artículo.
        $producto = Producto::factory()->create();
        Articulo::factory()->create(['producto_id' => $producto->id]);

        expect(fn () => Articulo::factory()->create(['producto_id' => $producto->id]))
            ->toThrow(ItemDuplicadoException::class);
    });

    test('varios artículos sí pueden estar sin ligar a la vez', function () {
        // El unique es parcial: sólo muerde cuando hay producto. Si contara los
        // nulos, el segundo almacén no podría abrir.
        Articulo::factory()->sinLigar()->count(3)->create();

        expect(Articulo::sinLigar()->count())->toBe(3);
    });

    test('un producto de Compras no se borra si Almacén lo guarda', function () {
        $producto = Producto::factory()->create();
        Articulo::factory()->create(['producto_id' => $producto->id]);

        expect(fn () => $producto->delete())->toThrow(QueryException::class);
    });
});
