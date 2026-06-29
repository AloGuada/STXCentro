<?php

use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Pago;
use App\Models\Proveedor;
use Illuminate\Support\Carbon;

describe('Factura::calcularFechaPago', function () {
    afterEach(fn () => Carbon::setTestNow());

    test('cuenta los días de crédito desde hoy y ajusta al viernes (corte miércoles)', function () {
        Carbon::setTestNow('2026-06-29'); // lunes
        // hoy + 30 = 2026-07-29 (miércoles) -> viernes de esa semana 2026-07-31
        $factura = Factura::factory()->make(['dias_credito' => 30]);

        expect($factura->calcularFechaPago()?->format('Y-m-d'))->toBe('2026-07-31');
    });

    test('respeta la fecha de la factura solo si el proveedor lo exige', function () {
        $proveedor = Proveedor::factory()->create(['respetar_fecha_factura' => true]);
        // fecha_factura miércoles 2026-07-29 + 0 -> viernes 2026-07-31
        $factura = Factura::factory()->make([
            'fecha_factura' => '2026-07-29',
            'dias_credito' => 0,
            'proveedor_id' => $proveedor->id,
        ]);
        $factura->setRelation('proveedor', $proveedor);

        expect($factura->calcularFechaPago()?->format('Y-m-d'))->toBe('2026-07-31');
    });

    test('no depende de fecha_factura cuando el proveedor no la exige', function () {
        Carbon::setTestNow('2026-06-29'); // lunes
        // sin fecha_factura: base = hoy + 15 = 2026-07-14 (martes) -> viernes 2026-07-17
        $factura = Factura::factory()->make(['fecha_factura' => null, 'dias_credito' => 15]);

        expect($factura->calcularFechaPago()?->format('Y-m-d'))->toBe('2026-07-17');
    });

    test('cae al default del proveedor si dias_credito no esta seteado', function () {
        Carbon::setTestNow('2026-06-29'); // lunes
        $proveedor = Proveedor::factory()->create(['dias_credito_default' => 45]);
        $factura = Factura::factory()->make(['dias_credito' => null, 'proveedor_id' => $proveedor->id]);
        $factura->setRelation('proveedor', $proveedor);

        // hoy + 45 = 2026-08-13 (jueves) -> viernes siguiente 2026-08-21
        expect($factura->calcularFechaPago()?->format('Y-m-d'))->toBe('2026-08-21');
    });
});

describe('OrdenCompra saldos', function () {
    test('total_facturado suma facturas activas e ignora canceladas', function () {
        $oc = OrdenCompra::factory()->pendienteFactura()->create(['total' => 10000]);

        Factura::factory()->create(['orden_compra_id' => $oc->id, 'total' => 3000, 'estatus' => 'pendiente_aprobacion']);
        Factura::factory()->create(['orden_compra_id' => $oc->id, 'total' => 2000, 'estatus' => 'pagada']);
        Factura::factory()->create(['orden_compra_id' => $oc->id, 'total' => 5000, 'estatus' => 'cancelada']);

        $oc->refresh();

        expect((float) $oc->total_facturado)->toBe(5000.0);
    });

    test('total_pagado suma solo pagos pagados sin pago_padre', function () {
        $oc = OrdenCompra::factory()->pendienteFactura()->create(['total' => 10000]);
        $factura = Factura::factory()->create(['orden_compra_id' => $oc->id, 'total' => 5000]);

        Pago::factory()->create([
            'pagable_type' => Factura::class,
            'pagable_id' => $factura->id,
            'monto_pago' => 2000,
            'estatus' => 'programado',
        ]);
        Pago::factory()->create([
            'pagable_type' => Factura::class,
            'pagable_id' => $factura->id,
            'monto_pago' => 3000,
            'estatus' => 'pagado',
        ]);

        $oc->refresh();

        expect((float) $oc->total_pagado)->toBe(3000.0);
    });

    test('saldo_pendiente es total menos total_pagado', function () {
        $oc = OrdenCompra::factory()->pendienteFactura()->create(['total' => 10000]);
        $factura = Factura::factory()->create(['orden_compra_id' => $oc->id, 'total' => 10000]);

        Pago::factory()->create([
            'pagable_type' => Factura::class,
            'pagable_id' => $factura->id,
            'monto_pago' => 4000,
            'estatus' => 'pagado',
        ]);

        $oc->refresh();

        expect((float) $oc->saldo_pendiente)->toBe(6000.0);
    });
});
