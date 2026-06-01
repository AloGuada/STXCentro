<?php

use App\Enums\Costos\BaseDiasCredito;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Pago;
use App\Models\Proveedor;

describe('Factura::calcularFechaPago', function () {
    test('base factura: suma dias_credito y ajusta al proximo viernes', function () {
        // 2026-02-17 (martes) + 30 = 2026-03-19 (jueves), next friday = 2026-03-20
        $factura = Factura::factory()->make([
            'fecha_factura' => '2026-02-17',
            'dias_credito' => 30,
            'base_dias_credito' => BaseDiasCredito::Factura,
        ]);

        $fecha = $factura->calcularFechaPago();

        expect($fecha?->format('Y-m-d'))->toBe('2026-03-20');
    });

    test('base factura: respeta fecha si ya cae en viernes', function () {
        // 2026-02-20 (viernes) + 0 = 2026-02-20 (viernes)
        $factura = Factura::factory()->make([
            'fecha_factura' => '2026-02-20',
            'dias_credito' => 0,
            'base_dias_credito' => BaseDiasCredito::Factura,
        ]);

        expect($factura->calcularFechaPago()?->format('Y-m-d'))->toBe('2026-02-20');
    });

    test('retorna null si base=factura y fecha_factura esta vacia', function () {
        $factura = Factura::factory()->make([
            'fecha_factura' => null,
            'dias_credito' => 15,
            'base_dias_credito' => BaseDiasCredito::Factura,
        ]);

        expect($factura->calcularFechaPago())->toBeNull();
    });

    test('cae al default del proveedor si dias_credito no esta seteado', function () {
        $proveedor = Proveedor::factory()->create(['dias_credito_default' => 45]);
        $factura = Factura::factory()->make([
            'fecha_factura' => '2026-02-17',
            'dias_credito' => null,
            'base_dias_credito' => BaseDiasCredito::Factura,
            'proveedor_id' => $proveedor->id,
        ]);
        $factura->setRelation('proveedor', $proveedor);

        // 2026-02-17 + 45 = 2026-04-03 (viernes) -> se mantiene
        expect($factura->calcularFechaPago()?->format('Y-m-d'))->toBe('2026-04-03');
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
