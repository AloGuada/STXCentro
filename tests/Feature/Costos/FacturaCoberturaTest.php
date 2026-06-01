<?php

use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\Factura;
use App\Models\Costos\FacturaDetalle;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use Illuminate\Support\Carbon;

function ocConUnaPartida(float $cantidad = 10): array
{
    $oc = OrdenCompra::factory()->pendienteFactura()->create();
    $partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'cantidad' => $cantidad,
        'precio_unitario' => 100,
        'subtotal' => $cantidad * 100,
    ]);

    return [$oc, $partida];
}

test('cobertura_completa devuelve false cuando no hay recepciones', function () {
    [$oc, $partida] = ocConUnaPartida();
    $factura = Factura::factory()->create(['orden_compra_id' => $oc->id]);
    FacturaDetalle::factory()->create([
        'factura_id' => $factura->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad' => 5,
    ]);

    expect($factura->cobertura_completa)->toBeFalse();
});

test('cobertura_completa devuelve true cuando recepciones cubren todas las partidas', function () {
    [$oc, $partida] = ocConUnaPartida();
    $factura = Factura::factory()->create(['orden_compra_id' => $oc->id]);
    FacturaDetalle::factory()->create([
        'factura_id' => $factura->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad' => 5,
    ]);

    $entrega = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    EntregaDetalle::create([
        'entrega_id' => $entrega->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 5,
    ]);

    expect($factura->cobertura_completa)->toBeTrue();
});

test('cobertura_completa respeta FIFO entre facturas activas', function () {
    [$oc, $partida] = ocConUnaPartida(10);

    Carbon::setTestNow('2026-02-16 10:00:00');
    $f1 = Factura::factory()->create(['orden_compra_id' => $oc->id]);
    FacturaDetalle::factory()->create([
        'factura_id' => $f1->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad' => 5,
    ]);

    Carbon::setTestNow('2026-02-17 10:00:00');
    $f2 = Factura::factory()->create(['orden_compra_id' => $oc->id]);
    FacturaDetalle::factory()->create([
        'factura_id' => $f2->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad' => 5,
    ]);
    Carbon::setTestNow();

    $entrega = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    EntregaDetalle::create([
        'entrega_id' => $entrega->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 5,
    ]);

    expect($f1->cobertura_completa)->toBeTrue();
    expect($f2->cobertura_completa)->toBeFalse();
});

test('factura cancelada no consume saldo en la cobertura FIFO', function () {
    [$oc, $partida] = ocConUnaPartida(10);

    $fCanc = Factura::factory()->create(['orden_compra_id' => $oc->id, 'estatus' => 'cancelada']);
    FacturaDetalle::factory()->create([
        'factura_id' => $fCanc->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad' => 7,
    ]);

    $factura = Factura::factory()->create(['orden_compra_id' => $oc->id]);
    FacturaDetalle::factory()->create([
        'factura_id' => $factura->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad' => 5,
    ]);

    $entrega = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    EntregaDetalle::create([
        'entrega_id' => $entrega->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 5,
    ]);

    expect($factura->cobertura_completa)->toBeTrue();
});
