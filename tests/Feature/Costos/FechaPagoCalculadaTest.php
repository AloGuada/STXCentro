<?php

use App\Enums\Costos\BaseDiasCredito;
use App\Models\Costos\Factura;
use App\Models\Costos\Factura\FechaPagoCalculada;
use App\Models\Proveedor;

function facturaConProveedor(array $attrs, array $proveedorAttrs = []): Factura
{
    $proveedor = Proveedor::factory()->create($proveedorAttrs);
    $factura = Factura::factory()->make(array_merge($attrs, ['proveedor_id' => $proveedor->id]));
    $factura->setRelation('proveedor', $proveedor);

    return $factura;
}

test('respetar_fecha_factura del proveedor fuerza la base a la fecha del CFDI', function () {
    $factura = facturaConProveedor([
        'fecha_factura' => '2026-02-17', // martes
        'dias_credito' => 0,
        'base_dias_credito' => BaseDiasCredito::Aprobacion, // debe ignorarse
        'aprobada_costos_at' => null,
    ], ['respetar_fecha_factura' => true]);

    // 2026-02-17 (martes) + 0 -> próximo viernes 2026-02-20
    expect((new FechaPagoCalculada($factura))->calcular()?->format('Y-m-d'))->toBe('2026-02-20');
});

test('base aprobacion retorna null si la factura no fue aprobada por costos', function () {
    $factura = facturaConProveedor([
        'fecha_factura' => '2026-02-17',
        'dias_credito' => 10,
        'base_dias_credito' => BaseDiasCredito::Aprobacion,
        'aprobada_costos_at' => null,
    ], ['respetar_fecha_factura' => false]);

    expect((new FechaPagoCalculada($factura))->calcular())->toBeNull();
});

test('base aprobacion usa aprobada_costos_at cuando existe', function () {
    $factura = facturaConProveedor([
        'dias_credito' => 0,
        'base_dias_credito' => BaseDiasCredito::Aprobacion,
        'aprobada_costos_at' => '2026-02-17 10:00:00', // martes
    ], ['respetar_fecha_factura' => false]);

    // 2026-02-17 (martes) + 0 -> próximo viernes 2026-02-20
    expect((new FechaPagoCalculada($factura))->calcular()?->format('Y-m-d'))->toBe('2026-02-20');
});
