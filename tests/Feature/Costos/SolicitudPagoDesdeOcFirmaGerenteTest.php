<?php

use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\ConfiguracionCostos;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Permiso;
use App\Models\Departamento;
use App\Models\User;
use App\Services\Costos\SolicitudPagoDesdeOrdenCompra;
use Illuminate\Support\Carbon;

/**
 * Crea una OC de contado con un detalle y devuelve la solicitud generada.
 */
function generarSolicitudDeOc(?string $fechaPago = null): App\Models\Costos\SolicitudPago
{
    $oc = OrdenCompra::factory()->create(['tipo_pago' => 'contado', 'total' => 4000]);
    OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'cantidad' => 1,
        'precio_unitario' => 4000,
        'subtotal' => 4000,
    ]);

    return app(SolicitudPagoDesdeOrdenCompra::class)->crear($oc, User::factory()->create()->id, 'transferencia', $fechaPago);
}

test('la solicitud de pago generada por OC lleva una sola firma: el gerente de compras configurado', function () {
    $gerente = User::factory()->create();
    ConfiguracionCostos::actual()->update(['gerente_compras_id' => $gerente->id]);

    $depto = Departamento::factory()->create();

    // El departamento SÍ tiene cadena de niveles configurada; debe ignorarse.
    AprobacionDepartamento::factory()->create([
        'departamento_id' => $depto->id,
        'permiso_id' => Permiso::factory()->create(['nivel' => 1, 'tipo_aprobacion' => 'solicitud_pago'])->id,
    ]);
    AprobacionDepartamento::factory()->create([
        'departamento_id' => $depto->id,
        'permiso_id' => Permiso::factory()->create(['nivel' => 2, 'tipo_aprobacion' => 'solicitud_pago'])->id,
    ]);

    $oc = OrdenCompra::factory()->create([
        'tipo_pago' => 'contado',
        'departamento_id' => $depto->id,
        'total' => 5000,
    ]);
    OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'cantidad' => 1,
        'precio_unitario' => 5000,
        'subtotal' => 5000,
    ]);

    $solicitud = app(SolicitudPagoDesdeOrdenCompra::class)->crear($oc, User::factory()->create()->id);

    $aprobaciones = $solicitud->aprobaciones()->get();

    expect($aprobaciones)->toHaveCount(1)
        ->and($aprobaciones->first()->aprobador_id)->toBe($gerente->id)
        ->and($aprobaciones->first()->nivel)->toBe(1)
        ->and($aprobaciones->first()->estatus->value)->toBe('pendiente');
});

test('sin gerente de compras configurado la solicitud de OC queda sin cadena', function () {
    ConfiguracionCostos::actual()->update(['gerente_compras_id' => null]);

    $oc = OrdenCompra::factory()->create(['tipo_pago' => 'contado', 'total' => 3000]);
    OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'cantidad' => 1,
        'precio_unitario' => 3000,
        'subtotal' => 3000,
    ]);

    $solicitud = app(SolicitudPagoDesdeOrdenCompra::class)->crear($oc, User::factory()->create()->id);

    expect($solicitud->aprobaciones()->count())->toBe(0);
});

test('sin fecha elegida la solicitud de OC toma el próximo viernes', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00')); // miércoles

    $solicitud = generarSolicitudDeOc();

    expect($solicitud->fecha_pago_solicitada->toDateString())->toBe('2026-07-10'); // viernes inmediato

    Carbon::setTestNow();
});

test('respeta la fecha de pago elegida aunque sea este viernes pasado el miércoles', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-08 13:30')); // miércoles, ya pasó el corte

    $solicitud = generarSolicitudDeOc('2026-07-10'); // este viernes

    expect($solicitud->fecha_pago_solicitada->toDateString())->toBe('2026-07-10'); // se respeta

    Carbon::setTestNow();
});

test('una fecha de pago ya vencida al crear se recorre al próximo viernes', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-13 09:00')); // lunes

    $solicitud = generarSolicitudDeOc('2026-07-10'); // viernes ya pasado

    expect($solicitud->fecha_pago_solicitada->toDateString())->toBe('2026-07-17'); // próximo viernes

    Carbon::setTestNow();
});

test('al aprobar, si la fecha de pago ya pasó, se recorre al próximo viernes', function () {
    $gerente = User::factory()->create();
    ConfiguracionCostos::actual()->update(['gerente_compras_id' => $gerente->id]);

    Carbon::setTestNow(Carbon::parse('2026-07-06 09:00')); // lunes: elige el viernes de esa semana
    $solicitud = generarSolicitudDeOc('2026-07-10');
    expect($solicitud->fecha_pago_solicitada->toDateString())->toBe('2026-07-10');

    // La firma llega la semana siguiente, cuando el viernes elegido ya pasó.
    Carbon::setTestNow(Carbon::parse('2026-07-14 10:00')); // martes siguiente
    $aprobacion = $solicitud->aprobaciones()->firstOrFail();
    $this->actingAs($gerente);
    app(App\Services\Costos\AprobacionService::class)->aprobar($aprobacion, 'Firmado');

    expect($solicitud->fresh()->fecha_pago_solicitada->toDateString())->toBe('2026-07-17'); // próximo viernes

    Carbon::setTestNow();
});

test('el endpoint de configuración guarda el gerente de compras', function () {
    $gerente = User::factory()->create();

    $this->actingAs(User::factory()->create())
        ->put('/admin/costos/configuracion', [
            'dias_apartado' => 5,
            'dias_cancelar_requisicion' => 10,
            'dias_cancelar_solicitud' => 10,
            'corte_activo' => true,
            'corte_dia' => 3,
            'corte_hora' => '13:00',
            'gerente_compras_id' => $gerente->id,
        ])
        ->assertRedirect();

    expect(ConfiguracionCostos::actual()->gerente_compras_id)->toBe($gerente->id);
});
