<?php

use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Proveedor;

test('OC pendiente_factura cuando no tiene facturas', function () {
    $oc = OrdenCompra::factory()->create(['estatus' => 'pendiente_entrega']);

    $oc->recalcularEstatus();
    $oc->refresh();

    expect($oc->estatus)->toBe('pendiente_factura');
});

test('OC pendiente_entrega cuando tiene factura sin entrega', function () {
    $oc = OrdenCompra::factory()->create();
    Factura::factory()->pendienteEntrega()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $oc->proveedor_id,
    ]);

    $oc->recalcularEstatus();
    $oc->refresh();

    expect($oc->estatus)->toBe('pendiente_entrega');
});

test('OC pendiente_aprobacion cuando todas las facturas tienen entrega completa', function () {
    $oc = OrdenCompra::factory()->create();
    Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $oc->proveedor_id,
        'estatus' => 'pendiente_aprobacion',
    ]);

    $oc->recalcularEstatus();
    $oc->refresh();

    expect($oc->estatus)->toBe('pendiente_aprobacion');
});

test('OC pendiente_entrega cuando alguna factura no tiene entrega', function () {
    $proveedor = Proveedor::factory()->create();
    $oc = OrdenCompra::factory()->create(['proveedor_id' => $proveedor->id]);

    $factura1 = Factura::factory()->pendienteEntrega()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $proveedor->id,
    ]);
    Entrega::factory()->create(['factura_id' => $factura1->id]);

    Factura::factory()->pendienteEntrega()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $proveedor->id,
    ]);

    $oc->recalcularEstatus();
    $oc->refresh();

    expect($oc->estatus)->toBe('pendiente_entrega');
});

test('OC pendiente_pago cuando todas las facturas estan aprobadas', function () {
    $proveedor = Proveedor::factory()->create();
    $oc = OrdenCompra::factory()->create(['proveedor_id' => $proveedor->id]);

    Factura::factory()->pendientePago()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $proveedor->id,
    ]);

    $oc->recalcularEstatus();
    $oc->refresh();

    expect($oc->estatus)->toBe('pendiente_pago');
});

test('OC pendiente_pago con mix de pendiente_pago y pagada', function () {
    $proveedor = Proveedor::factory()->create();
    $oc = OrdenCompra::factory()->create(['proveedor_id' => $proveedor->id]);

    Factura::factory()->pendientePago()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $proveedor->id,
    ]);

    Factura::factory()->pagada()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $proveedor->id,
    ]);

    $oc->recalcularEstatus();
    $oc->refresh();

    expect($oc->estatus)->toBe('pendiente_pago');
});

test('OC pagada cuando todas las facturas estan pagadas', function () {
    $proveedor = Proveedor::factory()->create();
    $oc = OrdenCompra::factory()->create(['proveedor_id' => $proveedor->id]);

    Factura::factory()->pagada()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $proveedor->id,
    ]);

    Factura::factory()->pagada()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $proveedor->id,
    ]);

    $oc->recalcularEstatus();
    $oc->refresh();

    expect($oc->estatus)->toBe('pagada');
});

test('factura cancelada no bloquea progreso de OC', function () {
    $proveedor = Proveedor::factory()->create();
    $oc = OrdenCompra::factory()->create(['proveedor_id' => $proveedor->id]);

    Factura::factory()->pagada()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $proveedor->id,
    ]);

    Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $proveedor->id,
        'estatus' => 'cancelada',
    ]);

    $oc->recalcularEstatus();
    $oc->refresh();

    expect($oc->estatus)->toBe('pagada');
});

test('OC pendiente_factura cuando todas facturas canceladas', function () {
    $oc = OrdenCompra::factory()->create();
    Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $oc->proveedor_id,
        'estatus' => 'cancelada',
    ]);

    $oc->recalcularEstatus();
    $oc->refresh();

    expect($oc->estatus)->toBe('pendiente_factura');
});
