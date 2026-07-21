<?php

use App\Models\Costos\Aprobacion;
use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\Requisicion;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\SolicitudPagoDetalle;
use App\Models\User;
use App\Services\Costos\ApprovalChainService;
use Inertia\Testing\AssertableInertia;

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

test('la firma adicional agrega una aprobacion nivel 0 al construir la cadena', function () {
    $solicitud = SolicitudPago::factory()->create([
        'firma_adicional_aprobador_id' => $this->aprobador->id,
    ]);

    app(ApprovalChainService::class)->crearCadenaAprobaciones($solicitud);

    $adicional = $solicitud->aprobaciones()->where('nivel', 0)->first();
    expect($adicional)->not->toBeNull();
    expect($adicional->es_adicional)->toBeTrue();
    expect($adicional->aprobador_id)->toBe($this->aprobador->id);
    expect($adicional->estatus->value)->toBe('pendiente');
});

test('sin firma adicional no se crea aprobacion nivel 0', function () {
    $solicitud = SolicitudPago::factory()->create(['firma_adicional_aprobador_id' => null]);

    app(ApprovalChainService::class)->crearCadenaAprobaciones($solicitud);

    expect($solicitud->aprobaciones()->where('nivel', 0)->exists())->toBeFalse();
});

test('la bandeja expone los archivos (media) de la requisicion pendiente', function () {
    // El gate 'aprobador-costos' exige estar asignado en aprobacion_departamento.
    AprobacionDepartamento::factory()->create(['aprobador_id' => $this->aprobador->id]);

    $requisicion = Requisicion::factory()->pendienteAprobacion()->create([
    ]);
    $requisicion->media()->create([
        'descripcion' => 'Cotizacion escaneada',
        'nombre_original' => 'cotizacion.pdf',
        'path' => "costos/requisiciones/{$requisicion->id}/cotizacion.pdf",
        'mime' => 'application/pdf',
        'size' => 1024,
    ]);

    $requisicion->aprobaciones()->create([
        'nivel' => 1,
        'aprobador_id' => $this->aprobador->id,
        'estatus' => 'pendiente',
    ]);

    $this->actingAs($this->aprobador)
        ->get(route('admin.costos.aprobaciones.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/costos/aprobaciones/index')
            ->has('pendientes', 1, fn (AssertableInertia $row) => $row
                ->where('tipo', 'requisicion')
                ->has('requisicion.media', 1, fn (AssertableInertia $m) => $m
                    ->where('nombre_original', 'cotizacion.pdf')
                    ->etc()
                )
                ->etc()
            )
        );
});

test('la firma adicional tambien aplica a requisiciones', function () {
    $requisicion = Requisicion::factory()->create([
        'firma_adicional_aprobador_id' => $this->aprobador->id,
    ]);

    app(ApprovalChainService::class)->crearCadenaAprobaciones($requisicion);

    $adicional = $requisicion->aprobaciones()->where('nivel', 0)->first();
    expect($adicional)->not->toBeNull();
    expect($adicional->es_adicional)->toBeTrue();
    expect($adicional->aprobador_id)->toBe($this->aprobador->id);
});
