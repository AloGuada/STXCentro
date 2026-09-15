<?php

use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Conteo;
use App\Models\Alm\ConteoDetalle;
use App\Models\Alm\Existencia;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Producto;
use App\Models\Costos\ProductoPrecio;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionDetalle;

/**
 * Fase 1 del reapunte a `items`: mientras conviven `item_id`, `producto_id` y
 * `articulo_id`, con cualquiera de las tres que traiga el renglón el trait
 * llena las otras. Es lo que permite que la fase 4 quite las viejas sin que
 * ningún flujo se haya quedado atrás.
 */
it('llena item y articulo cuando la existencia nace con producto', function (): void {
    $producto = Producto::factory()->create();

    $existencia = Existencia::query()->create(['almacen_id' => Almacen::factory()->create()->id, 'producto_id' => $producto->id]);

    expect($existencia->item_id)->toBe($producto->item_id)
        ->and(Articulo::query()->where('item_id', $producto->item_id)->value('id'))->toBe($existencia->articulo_id)
        ->and($existencia->item->id)->toBe($producto->item_id);
});

it('llena item y producto cuando la existencia nace con articulo', function (): void {
    $articulo = Articulo::factory()->create();

    $existencia = Existencia::query()->create(['almacen_id' => Almacen::factory()->create()->id, 'articulo_id' => $articulo->id]);

    expect($existencia->item_id)->toBe($articulo->item_id)
        ->and($existencia->producto_id)->toBe($articulo->producto_id);
});

it('llena articulo y producto cuando la existencia nace solo con item', function (): void {
    $articulo = Articulo::factory()->create();

    $existencia = Existencia::query()->create(['almacen_id' => Almacen::factory()->create()->id, 'item_id' => $articulo->item_id]);

    expect($existencia->articulo_id)->toBe($articulo->id)
        ->and($existencia->producto_id)->toBe($articulo->producto_id);
});

it('llena item en las hijas de Costos desde el producto y al reves', function (): void {
    $producto = Producto::factory()->create();

    $precio = ProductoPrecio::factory()->create(['producto_id' => $producto->id]);
    $partidaReq = RequisicionDetalle::factory()->create(['requisicion_id' => Requisicion::factory()->create()->id, 'item_id' => $producto->item_id]);
    $partidaOc = OrdenCompraDetalle::factory()->create(['orden_compra_id' => OrdenCompra::factory()->create()->id, 'producto_id' => $producto->id]);

    expect($precio->item_id)->toBe($producto->item_id)
        ->and($partidaReq->producto_id)->toBe($producto->id)
        ->and($partidaOc->item_id)->toBe($producto->item_id);
});

it('llena item en las tablas que solo tienen articulo', function (): void {
    $articulo = Articulo::factory()->create();
    $conteo = Conteo::factory()->create();

    $renglon = ConteoDetalle::query()->create([
        'conteo_id' => $conteo->id,
        'articulo_id' => $articulo->id,
        'orden' => 1,
        'cantidad_sistema' => 0,
    ]);

    expect($renglon->item_id)->toBe($articulo->item_id);
});

it('deja las tres vacias en una partida sin producto', function (): void {
    $flete = OrdenCompraDetalle::factory()->create(['orden_compra_id' => OrdenCompra::factory()->create()->id, 'producto_id' => null]);

    expect($flete->item_id)->toBeNull();
});

it('verificar-articulos cuadra con todo lleno', function (): void {
    Existencia::query()->create(['almacen_id' => Almacen::factory()->create()->id, 'producto_id' => Producto::factory()->create()->id]);

    $this->artisan('alm:verificar-articulos')
        ->expectsOutputToContain('Las trece cuadran')
        ->assertSuccessful();
});
