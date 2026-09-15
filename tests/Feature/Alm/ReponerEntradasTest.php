<?php

use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Producto;
use App\Services\Alm\AlmacenLedger;
use App\Services\Alm\RegistradorEntradaAlmacen;

/**
 * Recepciones que se quedaron a medias en el kardex: `aplicar()` es idempotente
 * por documento, así que un renglón que se saltó (bandera de inventario
 * apagada, partida sin producto) no se rescataba solo. `alm:reponer-entradas`
 * asienta renglón por renglón lo que falta.
 */
beforeEach(function (): void {
    $this->almacen = Almacen::factory()->create();
    $this->orden = OrdenCompra::factory()->pendienteEntrega()->create(['moneda' => 'mxn', 'total' => 100000]);
});

function recepcionCon(array $renglones, ?Almacen $almacen = null): Entrega
{
    $entrega = Entrega::factory()->create([
        'orden_compra_id' => test()->orden->id,
        'almacen_id' => ($almacen ?? test()->almacen)->id,
        'tipo' => 'parcial',
    ]);

    foreach ($renglones as [$producto, $cantidad]) {
        $partida = OrdenCompraDetalle::factory()->create([
            'orden_compra_id' => test()->orden->id,
            'producto_id' => $producto->id,
            'cantidad' => $cantidad,
            'precio_unitario' => 10,
            'subtotal' => $cantidad * 10,
        ]);

        EntregaDetalle::factory()->create([
            'entrega_id' => $entrega->id,
            'orden_compra_detalle_id' => $partida->id,
            'producto_id' => $producto->id,
            'cantidad_recibida' => $cantidad,
        ]);
    }

    return $entrega->fresh();
}

function saldoDe(Producto $producto, ?Almacen $almacen = null): float
{
    return (float) Existencia::query()
        ->where('almacen_id', ($almacen ?? test()->almacen)->id)
        ->where('articulo_id', Articulo::query()->where('item_id', $producto->item_id)->value('id'))
        ->value('cantidad');
}

it('repone sólo el renglón que se quedó sin asiento y no vuelve a tocar el que ya lo tenía', function (): void {
    $conAsiento = Producto::factory()->create();
    $sinAsiento = Producto::factory()->create();
    $entrega = recepcionCon([[$conAsiento, 5], [$sinAsiento, 8]]);

    app(AlmacenLedger::class)->registrarPorProducto(
        almacenId: $this->almacen->id,
        productoId: $conAsiento->id,
        tipo: MovimientoTipo::Entrada,
        cantidad: 5,
        documento: $entrega,
    );

    expect(app(RegistradorEntradaAlmacen::class)->aplicar($entrega))->toBe(0);

    $this->artisan('alm:reponer-entradas')
        ->expectsOutputToContain('1 renglones sin asiento en 1 recepciones')
        ->expectsOutputToContain('Simulacro')
        ->assertSuccessful();

    expect(Movimiento::query()->count())->toBe(1);

    $this->artisan('alm:reponer-entradas', ['--force' => true])
        ->expectsOutputToContain('1 renglones asentados')
        ->assertSuccessful();

    expect(Movimiento::query()->count())->toBe(2)
        ->and(saldoDe($conAsiento))->toBe(5.0)
        ->and(saldoDe($sinAsiento))->toBe(8.0);

    $this->artisan('alm:reponer-entradas', ['--force' => true])
        ->expectsOutputToContain('nada que reponer')
        ->assertSuccessful();

    expect(Movimiento::query()->count())->toBe(2);
});

it('empareja por cantidad cuando el mismo producto viene en varios renglones', function (): void {
    $argon = Producto::factory()->create();
    $entrega = recepcionCon([[$argon, 199.54], [$argon, 465.59], [$argon, 33.25]]);

    app(AlmacenLedger::class)->registrarPorProducto(
        almacenId: $this->almacen->id,
        productoId: $argon->id,
        tipo: MovimientoTipo::Entrada,
        cantidad: 465.59,
        documento: $entrega,
    );

    $this->artisan('alm:reponer-entradas', ['--force' => true])
        ->expectsOutputToContain('2 renglones asentados')
        ->assertSuccessful();

    expect(saldoDe($argon))->toBe(199.54 + 465.59 + 33.25);
});

it('no toca recepciones canceladas ni recepciones sin almacén ni renglones sin producto', function (): void {
    $producto = Producto::factory()->create();

    $cancelada = recepcionCon([[$producto, 3]]);
    $cancelada->forceFill(['cancelada_at' => now()])->save();

    $sinAlmacen = recepcionCon([[$producto, 4]]);
    $sinAlmacen->forceFill(['almacen_id' => null])->save();

    $flete = recepcionCon([[$producto, 2]]);
    EntregaDetalle::query()->where('entrega_id', $flete->id)->update(['producto_id' => null]);
    OrdenCompraDetalle::query()->whereIn('id', EntregaDetalle::query()->where('entrega_id', $flete->id)->select('orden_compra_detalle_id'))->update(['producto_id' => null]);

    $this->artisan('alm:reponer-entradas', ['--force' => true])
        ->expectsOutputToContain('nada que reponer')
        ->assertSuccessful();

    expect(Movimiento::query()->count())->toBe(0);
});

it('abre la existencia por artículo cuando la carga inicial ya la tenía sin producto', function (): void {
    $producto = Producto::factory()->create();
    $articulo = Articulo::query()->where('item_id', $producto->item_id)->firstOr(fn () => Articulo::factory()->create(['item_id' => $producto->item_id, 'producto_id' => $producto->id]));

    // Como quedó la carga inicial en producción: fila por artículo, sin producto.
    $existencia = Existencia::query()->create(['almacen_id' => $this->almacen->id, 'articulo_id' => $articulo->id]);
    $existencia->forceFill(['producto_id' => null, 'cantidad' => 10, 'valor' => 100, 'costo_promedio' => 10])->saveQuietly();

    $movimiento = app(AlmacenLedger::class)->registrarPorProducto(
        almacenId: $this->almacen->id,
        productoId: $producto->id,
        tipo: MovimientoTipo::Entrada,
        cantidad: 5,
        costoUnitario: 10,
    );

    expect($movimiento->existencia_id)->toBe($existencia->id)
        ->and(Existencia::query()->where('almacen_id', $this->almacen->id)->count())->toBe(1)
        ->and((float) $existencia->fresh()->cantidad)->toBe(15.0)
        ->and($existencia->fresh()->producto_id)->toBe($producto->id);
});
