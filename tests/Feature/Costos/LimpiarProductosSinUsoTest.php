<?php

use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Producto;
use App\Models\Costos\ProductoPrecio;
use App\Models\Costos\RequisicionDetalle;

function productoViejo(array $attrs = []): Producto
{
    return Producto::factory()->create([...$attrs, 'created_at' => now()->subDays(10)]);
}

test('borra productos viejos sin precio ni compra', function () {
    $sinUso = productoViejo(['descripcion' => 'Tornillo duplicado']);

    $this->artisan('costos:limpiar-productos', ['--force' => true])
        ->assertExitCode(0);

    $this->assertDatabaseMissing('costos_productos', ['id' => $sinUso->id]);
});

test('conserva productos creados hace menos de 7 dias', function () {
    $reciente = Producto::factory()->create(['created_at' => now()->subDays(3)]);

    $this->artisan('costos:limpiar-productos', ['--force' => true])->assertExitCode(0);

    $this->assertDatabaseHas('costos_productos', ['id' => $reciente->id]);
});

test('conserva productos con precio (cotizados)', function () {
    $conPrecio = productoViejo();
    ProductoPrecio::factory()->create(['producto_id' => $conPrecio->id]);

    $this->artisan('costos:limpiar-productos', ['--force' => true])->assertExitCode(0);

    $this->assertDatabaseHas('costos_productos', ['id' => $conPrecio->id]);
});

test('conserva productos comprados (referenciados por una OC)', function () {
    $comprado = productoViejo();
    OrdenCompraDetalle::factory()->create(['producto_id' => $comprado->id]);

    $this->artisan('costos:limpiar-productos', ['--force' => true])->assertExitCode(0);

    $this->assertDatabaseHas('costos_productos', ['id' => $comprado->id]);
});

test('borra un producto referenciado solo por una partida de requisicion y deja la partida con producto_id null', function () {
    $sinUso = productoViejo();
    $detalle = RequisicionDetalle::factory()->create([
        'producto_id' => $sinUso->id,
        'descripcion' => 'Tornillo Especial',
    ]);

    $this->artisan('costos:limpiar-productos', ['--force' => true])->assertExitCode(0);

    $this->assertDatabaseMissing('costos_productos', ['id' => $sinUso->id]);
    // La partida sobrevive con su texto; solo se desliga el catálogo.
    $this->assertDatabaseHas('costos_requisicion_detalle', [
        'id' => $detalle->id,
        'producto_id' => null,
        'descripcion' => 'Tornillo Especial',
    ]);
});

test('el dry-run no borra nada', function () {
    $sinUso = productoViejo();

    $this->artisan('costos:limpiar-productos')->assertExitCode(0);

    $this->assertDatabaseHas('costos_productos', ['id' => $sinUso->id]);
});
