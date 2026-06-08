<?php

use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Pago;
use App\Models\Costos\SolicitudPago;

test('total_pagado incluye el anticipo de contado pagado y avanza el porcentaje de pago', function () {
    $oc = OrdenCompra::factory()->create([
        'tipo_pago' => 'contado',
        'estatus' => 'pendiente_entrega',
        'total' => 5800,
    ]);
    OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'cantidad' => 1,
        'precio_unitario' => 5000,
        'subtotal' => 5000,
    ]);
    $sp = SolicitudPago::factory()->create([
        'orden_compra_id' => $oc->id,
        'monto_total' => 5800,
    ]);

    Pago::factory()->create([
        'pagable_type' => SolicitudPago::class,
        'pagable_id' => $sp->id,
        'monto_pago' => 5800,
        'estatus' => 'pagado',
    ]);

    $oc->refresh();

    expect((float) $oc->total_pagado)->toBe(5800.0);
    expect($oc->porcentaje_pago)->toBe(100.0);
});

test('un anticipo de contado NO pagado no cuenta en total_pagado', function () {
    $oc = OrdenCompra::factory()->create(['tipo_pago' => 'contado', 'total' => 5000]);
    $sp = SolicitudPago::factory()->create(['orden_compra_id' => $oc->id, 'monto_total' => 5000]);

    Pago::factory()->create([
        'pagable_type' => SolicitudPago::class,
        'pagable_id' => $sp->id,
        'monto_pago' => 5000,
        'estatus' => 'programado',
    ]);

    expect((float) $oc->refresh()->total_pagado)->toBe(0.0);
});

test('total_pagado de OC de credito solo cuenta pagos de facturas', function () {
    $oc = OrdenCompra::factory()->create(['total' => 5000]);
    $factura = Factura::factory()->create(['orden_compra_id' => $oc->id, 'total' => 5000]);

    Pago::factory()->create([
        'pagable_type' => Factura::class,
        'pagable_id' => $factura->id,
        'monto_pago' => 3000,
        'estatus' => 'pagado',
    ]);

    expect((float) $oc->refresh()->total_pagado)->toBe(3000.0);
});
