<?php

use App\Enums\Costos\BaseDiasCredito;
use App\Models\Costos\Factura;
use App\Models\Costos\Factura\FechaPagoCalculada;
use App\Models\Proveedor;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

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

test('por defecto la base es HOY y el término 30 días, ajustando a viernes', function () {
    Carbon::setTestNow('2026-06-29'); // lunes
    // hoy + 30 = 2026-07-29 (miércoles) -> viernes de esa semana 2026-07-31
    $factura = facturaConProveedor(
        ['dias_credito' => null, 'fecha_factura' => '2026-01-01'],
        ['respetar_fecha_factura' => false, 'dias_credito_default' => 0],
    );

    expect((new FechaPagoCalculada($factura))->calcular()?->format('Y-m-d'))->toBe('2026-07-31');
});

test('si la fecha cae el miércoles, paga ese mismo viernes', function () {
    // base = fecha_factura miércoles 2026-07-29 + 0 -> viernes 2026-07-31
    $factura = facturaConProveedor(['fecha_factura' => '2026-07-29', 'dias_credito' => 0], ['respetar_fecha_factura' => true]);

    expect((new FechaPagoCalculada($factura))->calcular()?->format('Y-m-d'))->toBe('2026-07-31');
});

test('si la fecha cae pasado el miércoles (jueves), paga el viernes siguiente', function () {
    // base = fecha_factura jueves 2026-07-30 + 0 -> viernes siguiente 2026-08-07
    $factura = facturaConProveedor(['fecha_factura' => '2026-07-30', 'dias_credito' => 0], ['respetar_fecha_factura' => true]);

    expect((new FechaPagoCalculada($factura))->calcular()?->format('Y-m-d'))->toBe('2026-08-07');
});

test('respeta los días de crédito configurados del proveedor', function () {
    Carbon::setTestNow('2026-06-29'); // lunes
    // hoy + 45 = 2026-08-13 (jueves) -> viernes siguiente 2026-08-21
    $factura = facturaConProveedor(
        ['dias_credito' => null],
        ['respetar_fecha_factura' => false, 'dias_credito_default' => 45],
    );

    expect((new FechaPagoCalculada($factura))->calcular()?->format('Y-m-d'))->toBe('2026-08-21');
});
