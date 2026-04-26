<?php

use App\Models\Costos\Aprobacion;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\SolicitudPagoDetalle;
use App\Models\User;

beforeEach(function () {
    $this->aprobador = User::factory()->create(['firma_path' => 'firmas/x.png']);
});

test('Aprobacion auto-fill aprobable desde solicitud_id legacy', function () {
    $solicitud = SolicitudPago::factory()->create();

    $aprobacion = Aprobacion::create([
        'solicitud_id' => $solicitud->id,
        'nivel' => 1,
        'aprobador_id' => $this->aprobador->id,
        'estatus' => 'pendiente',
    ]);

    expect($aprobacion->aprobable_type)->toBe(SolicitudPago::class);
    expect($aprobacion->aprobable_id)->toBe($solicitud->id);
    expect($aprobacion->aprobable->is($solicitud))->toBeTrue();
});

test('SolicitudPago.aprobaciones() devuelve cadena polimorfica', function () {
    $solicitud = SolicitudPago::factory()->create();

    Aprobacion::create([
        'aprobable_type' => SolicitudPago::class,
        'aprobable_id' => $solicitud->id,
        'nivel' => 1,
        'aprobador_id' => $this->aprobador->id,
        'estatus' => 'pendiente',
    ]);

    expect($solicitud->aprobaciones()->count())->toBe(1);
    expect($solicitud->cadenaAprobacion()->count())->toBe(1);
});

test('SolicitudPago.tipoAprobacion devuelve solicitud_pago', function () {
    $s = SolicitudPago::factory()->make();
    expect($s->tipoAprobacion())->toBe('solicitud_pago');
});

test('onAprobacionCompleta transiciona a aprobada y aplica impacto presupuestal', function () {
    $solicitud = SolicitudPago::factory()->create([
        'estatus' => 'pendiente_firma',
        'monto_total' => 1000,
    ]);
    SolicitudPagoDetalle::factory()->create([
        'solicitud_id' => $solicitud->id,
        'subtotal' => 1000,
    ]);

    $solicitud->onAprobacionCompleta($this->aprobador->id);

    expect($solicitud->fresh()->estatus->value)->toBe('aprobada');
    expect($solicitud->rubrosAfectados()->count())->toBeGreaterThan(0);
});

test('onAprobacionRechazada cancela la solicitud', function () {
    $solicitud = SolicitudPago::factory()->create(['estatus' => 'pendiente_firma']);

    $solicitud->onAprobacionRechazada('precio fuera de rango', $this->aprobador->id);

    expect($solicitud->fresh()->estatus->value)->toBe('cancelada');
});
