<?php

use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Producto;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionDetalle;

/**
 * Partidas que nacieron sin producto (antes de que la requisición lo exigiera)
 * se emparejan por descripción con el catálogo; lo que no casa se lista y se
 * queda como está.
 */
it('asigna el producto por nombre a la partida de orden, a su requisición y a sus recepciones', function (): void {
    $producto = Producto::factory()->create(['codigo' => 'ART-00454', 'descripcion' => 'GUANTES DE BOLITA']);

    $requisicion = Requisicion::factory()->create(['estatus' => 'liberada']);
    $partidaReq = RequisicionDetalle::factory()->create([
        'requisicion_id' => $requisicion->id,
        'producto_id' => null,
        'codigo_producto' => null,
        'descripcion' => 'Guantes  de bolita',
    ]);

    $orden = OrdenCompra::factory()->pendienteEntrega()->create();
    $partidaOc = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $orden->id,
        'requisicion_detalle_id' => $partidaReq->id,
        'producto_id' => null,
        'codigo_producto' => null,
        'descripcion' => 'GUANTES DE BOLITA',
    ]);
    $flete = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $orden->id,
        'producto_id' => null,
        'descripcion' => 'ENVIO DE MERCANCIA',
    ]);

    $entrega = Entrega::factory()->create(['orden_compra_id' => $orden->id]);
    $renglon = EntregaDetalle::factory()->create([
        'entrega_id' => $entrega->id,
        'orden_compra_detalle_id' => $partidaOc->id,
        'producto_id' => null,
    ]);

    $this->artisan('costos:asignar-producto-a-partidas')
        ->expectsOutputToContain('ENVIO DE MERCANCIA')
        ->expectsOutputToContain('Simulacro')
        ->assertSuccessful();

    expect($partidaOc->fresh()->producto_id)->toBeNull();

    $this->artisan('costos:asignar-producto-a-partidas', ['--force' => true])
        ->expectsOutputToContain('1 partidas de orden, 1 de requisición y 1 renglones de recepción')
        ->assertSuccessful();

    expect($partidaOc->fresh())
        ->producto_id->toBe($producto->id)
        ->codigo_producto->toBe('ART-00454')
        ->descripcion->toBe('GUANTES DE BOLITA')
        ->and($partidaReq->fresh())
        ->producto_id->toBe($producto->id)
        ->descripcion->toBe('Guantes  de bolita')
        ->and($renglon->fresh()->producto_id)->toBe($producto->id)
        ->and($flete->fresh()->producto_id)->toBeNull();
});

it('no toca partidas de órdenes o requisiciones canceladas ni productos inactivos', function (): void {
    Producto::factory()->create(['descripcion' => 'CINTA AISLANTE']);
    Producto::factory()->create(['descripcion' => 'LIJA 80', 'activo' => false]);

    $cancelada = OrdenCompra::factory()->create(['estatus' => 'cancelada']);
    $partidaCancelada = OrdenCompraDetalle::factory()->create(['orden_compra_id' => $cancelada->id, 'producto_id' => null, 'descripcion' => 'CINTA AISLANTE']);

    $requisicion = Requisicion::factory()->create(['estatus' => 'liberada']);
    $partidaInactiva = RequisicionDetalle::factory()->create(['requisicion_id' => $requisicion->id, 'producto_id' => null, 'descripcion' => 'LIJA 80']);

    $this->artisan('costos:asignar-producto-a-partidas', ['--force' => true])
        ->expectsOutputToContain('nada que asignar')
        ->assertSuccessful();

    expect($partidaCancelada->fresh()->producto_id)->toBeNull()
        ->and($partidaInactiva->fresh()->producto_id)->toBeNull();
});
