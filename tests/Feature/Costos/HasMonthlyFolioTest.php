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

    expect($primera->folio)->toBe('OC-20260401');
    expect($segunda->folio)->toBe('OC-20260402');

    Carbon::setTestNow('2026-05-03');
    $otraMes = OrdenCompra::factory()->create();
    expect($otraMes->folio)->toBe('OC-20260501');

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

    expect($oc->folio)->toStartWith('OC-202604');
    expect($factura->folio)->toStartWith('FA-202604');
    expect($pago->folio)->toStartWith('PG-202604');
    expect($solicitud->folio)->toStartWith('SP-202604');
    expect($afectacion->folio)->toStartWith('AF-202604');

    Carbon::setTestNow();
});
