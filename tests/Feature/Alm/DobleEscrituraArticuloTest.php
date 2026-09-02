<?php

use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\Costos\Producto;
use App\Services\Alm\ResolvedorArticulo;

describe('la columna nueva se llena sola', function () {
    test('un renglón guardado sin artículo lo consigue del producto', function () {
        $producto = Producto::factory()->create();
        $articulo = Articulo::factory()->create(['producto_id' => $producto->id]);

        // Se escribe como escribe cualquier servicio hoy: sólo producto_id.
        $existencia = Existencia::create([
            'almacen_id' => Almacen::factory()->create()->id,
            'producto_id' => $producto->id,
        ]);

        expect($existencia->articulo_id)->toBe($articulo->id);
    });

    test('si el producto nunca ha pisado la bodega, le crea su artículo', function () {
        // Es el caso de la recepción: llega material de una orden, el producto
        // viene conocido, y el artículo nace ligado sin que nadie lo pida.
        $producto = Producto::factory()->create(['descripcion' => 'SIERRA LAMA', 'unidad' => 'PZA']);

        expect(Articulo::where('producto_id', $producto->id)->exists())->toBeFalse();

        $existencia = Existencia::create([
            'almacen_id' => Almacen::factory()->create()->id,
            'producto_id' => $producto->id,
        ]);

        $articulo = Articulo::where('producto_id', $producto->id)->first();

        expect($articulo)->not->toBeNull()
            ->and($existencia->articulo_id)->toBe($articulo->id)
            ->and($articulo->descripcion)->toBe('SIERRA LAMA');
    });

    test('no pisa el artículo que ya venía puesto', function () {
        $producto = Producto::factory()->create();
        $otro = Articulo::factory()->sinLigar()->create();

        $existencia = Existencia::create([
            'almacen_id' => Almacen::factory()->create()->id,
            'producto_id' => $producto->id,
            'articulo_id' => $otro->id,
        ]);

        expect($existencia->articulo_id)->toBe($otro->id);
    });

    test('las siete tablas lo hacen, no sólo existencias', function () {
        // El trait va en los siete modelos: si alguno se quedó fuera, aquí se ve.
        $producto = Producto::factory()->create();
        $almacen = Almacen::factory()->create();

        $existencia = Existencia::create([
            'almacen_id' => $almacen->id,
            'producto_id' => $producto->id,
        ]);

        $movimiento = Movimiento::create([
            'existencia_id' => $existencia->id,
            'almacen_id' => $almacen->id,
            'producto_id' => $producto->id,
            'tipo' => 'entrada',
            'cantidad' => 1,
            'saldo_antes' => 0,
            'saldo_despues' => 1,
        ]);

        expect($movimiento->articulo_id)
            ->not->toBeNull()
            ->toBe($existencia->articulo_id);
    });

    test('el resolvedor no consulta dos veces por el mismo producto', function () {
        // Un import de layout resolvería el mismo producto cientos de veces.
        $producto = Producto::factory()->create();
        $resolvedor = app(ResolvedorArticulo::class);

        $primero = $resolvedor->paraProducto($producto->id);

        // Se borra el artículo por debajo: si volviera a consultar, lo crearía
        // otra vez con id distinto. El caché tiene que devolver el de antes.
        Articulo::whereKey($primero)->delete();

        expect($resolvedor->paraProducto($producto->id))->toBe($primero);
    });

    test('un producto que no existe no inventa artículo', function () {
        expect(app(ResolvedorArticulo::class)->paraProducto(999999))->toBeNull();
    });
});
