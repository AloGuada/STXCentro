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
    $oc = OrdenCompra::factory()->pendienteEntrega()->create();
    $partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'cantidad' => $cantidad,
        'precio_unitario' => 100,
        'subtotal' => $cantidad * 100,
    ]);

    return [$oc, $partida];
}

test('factura sin recepciones queda en pendiente_entrega', function () {
    [$oc, $partida] = ocConUnaPartida();
    $factura = Factura::factory()->create(['orden_compra_id' => $oc->id, 'estatus' => 'pendiente_entrega']);
    FacturaDetalle::factory()->create([
        'factura_id' => $factura->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad' => 5,
    ]);

    $factura->load('detalles')->recalcularEstatus();

    expect($factura->fresh()->estatus->value)->toBe('pendiente_entrega');
});

test('factura se promueve al registrar recepción suficiente', function () {
    [$oc, $partida] = ocConUnaPartida();
    $factura = Factura::factory()->create(['orden_compra_id' => $oc->id, 'estatus' => 'pendiente_entrega']);
    FacturaDetalle::factory()->create([
        'factura_id' => $factura->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad' => 5,
    ]);

    // Registrar recepción suficiente dispara el observer
    $entrega = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    EntregaDetalle::create([
        'entrega_id' => $entrega->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 5,
    ]);

    expect($factura->fresh()->estatus->value)->toBe('pendiente_aprobacion');
});

test('factura con recepción parcial se queda en pendiente_entrega', function () {
    [$oc, $partida] = ocConUnaPartida();
    $factura = Factura::factory()->create(['orden_compra_id' => $oc->id, 'estatus' => 'pendiente_entrega']);
    FacturaDetalle::factory()->create([
        'factura_id' => $factura->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad' => 10,
    ]);

    $entrega = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    EntregaDetalle::create([
        'entrega_id' => $entrega->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 6,
    ]);

    expect($factura->fresh()->estatus->value)->toBe('pendiente_entrega');
});

test('factura se promueve cuando entregas acumuladas cubren el total', function () {
    [$oc, $partida] = ocConUnaPartida();
    $factura = Factura::factory()->create(['orden_compra_id' => $oc->id, 'estatus' => 'pendiente_entrega']);
    FacturaDetalle::factory()->create([
        'factura_id' => $factura->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad' => 10,
    ]);

    $e1 = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    EntregaDetalle::create(['entrega_id' => $e1->id, 'orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 6]);
    expect($factura->fresh()->estatus->value)->toBe('pendiente_entrega');

    $e2 = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    EntregaDetalle::create(['entrega_id' => $e2->id, 'orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => 4]);
    expect($factura->fresh()->estatus->value)->toBe('pendiente_aprobacion');
});

test('cobertura FIFO: primera factura cubierta, segunda espera recepción adicional', function () {
    [$oc, $partida] = ocConUnaPartida(10);

    // Primera factura creada ayer cubriendo 5
    Carbon::setTestNow('2026-02-16 10:00:00');
    $f1 = Factura::factory()->create(['orden_compra_id' => $oc->id, 'estatus' => 'pendiente_entrega']);
    FacturaDetalle::factory()->create([
        'factura_id' => $f1->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad' => 5,
    ]);

    // Segunda factura un día después cubriendo 5
    Carbon::setTestNow('2026-02-17 10:00:00');
    $f2 = Factura::factory()->create(['orden_compra_id' => $oc->id, 'estatus' => 'pendiente_entrega']);
    FacturaDetalle::factory()->create([
        'factura_id' => $f2->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad' => 5,
    ]);
    Carbon::setTestNow();

    // Llega recepción de 6 → alcanza para f1 (5), sobran 1 para f2 (necesita 5)
    $entrega = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    EntregaDetalle::create([
        'entrega_id' => $entrega->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 6,
    ]);

    expect($f1->fresh()->estatus->value)->toBe('pendiente_aprobacion');
    expect($f2->fresh()->estatus->value)->toBe('pendiente_entrega');

    // Llega recepción adicional que completa f2
    $entrega2 = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    EntregaDetalle::create([
        'entrega_id' => $entrega2->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 4,
    ]);

    expect($f2->fresh()->estatus->value)->toBe('pendiente_aprobacion');
});

test('factura nace promovida si las recepciones previas ya la cubren', function () {
    [$oc, $partida] = ocConUnaPartida(10);

    // Recepción previa de 5
    $entrega = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    EntregaDetalle::create([
        'entrega_id' => $entrega->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 5,
    ]);

    // Crear factura pendiente_entrega con 5 y llamar recalcularEstatus
    $factura = Factura::factory()->create(['orden_compra_id' => $oc->id, 'estatus' => 'pendiente_entrega']);
    FacturaDetalle::factory()->create([
        'factura_id' => $factura->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad' => 5,
    ]);

    $factura->load('detalles')->recalcularEstatus();

    expect($factura->fresh()->estatus->value)->toBe('pendiente_aprobacion');
});

test('factura cancelada no consume saldo en la cobertura FIFO', function () {
    [$oc, $partida] = ocConUnaPartida(10);

    // Factura vieja cancelada que facturaba 7
    $fCanc = Factura::factory()->create(['orden_compra_id' => $oc->id, 'estatus' => 'cancelada']);
    FacturaDetalle::factory()->create([
        'factura_id' => $fCanc->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad' => 7,
    ]);

    // Factura nueva de 5, que debería cubrirse con recepción de 5 (la cancelada no cuenta)
    $factura = Factura::factory()->create(['orden_compra_id' => $oc->id, 'estatus' => 'pendiente_entrega']);
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

    expect($factura->fresh()->estatus->value)->toBe('pendiente_aprobacion');
});
