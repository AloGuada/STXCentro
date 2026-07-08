<?php

use App\Models\Costos\AfectacionPresupuestal;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Pago;
use App\Models\Costos\SolicitudPago;
use Illuminate\Support\Carbon;

test('genera folio con prefijo mensual y secuencia que reinicia cada mes', function () {
    Carbon::setTestNow('2026-04-10');

    $primera = OrdenCompra::factory()->create();
    $segunda = OrdenCompra::factory()->create();

    expect($primera->folio)->toBe('OC-260401');
    expect($segunda->folio)->toBe('OC-260402');

    Carbon::setTestNow('2026-05-03');
    $otraMes = OrdenCompra::factory()->create();
    expect($otraMes->folio)->toBe('OC-260501');

    Carbon::setTestNow();
});

test('la secuencia continua correctamente al pasar de 99 a 100 y mas', function () {
    Carbon::setTestNow('2026-07-05');

    SolicitudPago::factory()->create(['folio' => 'SP-260799']);
    SolicitudPago::factory()->create(['folio' => 'SP-2607100']);

    $siguiente = SolicitudPago::factory()->create();

    expect($siguiente->folio)->toBe('SP-2607101');

    Carbon::setTestNow();
});

test('respeta el folio si ya fue asignado manualmente', function () {
    $oc = OrdenCompra::factory()->create(['folio' => 'OC-CUSTOM-99']);

    expect($oc->folio)->toBe('OC-CUSTOM-99');
});

test('cada modelo con el trait usa su propio prefijo', function () {
    Carbon::setTestNow('2026-04-10');

    $oc = OrdenCompra::factory()->create();
    $factura = Factura::factory()->create();
    $pago = Pago::factory()->create();
    $solicitud = SolicitudPago::factory()->create();
    $afectacion = AfectacionPresupuestal::factory()->create();

    expect($oc->folio)->toStartWith('OC-2604');
    expect($factura->folio)->toStartWith('FA-2604');
    expect($pago->folio)->toStartWith('PG-2604');
    expect($solicitud->folio)->toStartWith('SP-2604');
    expect($afectacion->folio)->toStartWith('AF-2604');

    Carbon::setTestNow();
});
