<?php

use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\OrdenCompraEstatus;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

test('crear OC registra una entrada created en activity_log', function () {
    $oc = OrdenCompra::factory()->create();

    $actividad = Activity::where('subject_type', OrdenCompra::class)
        ->where('subject_id', $oc->id)
        ->where('event', 'created')
        ->first();

    expect($actividad)->not->toBeNull();
    expect($actividad->log_name)->toBe('costos');
    expect($actividad->description)->toContain($oc->folio);
});

test('transitionTo registra un updated con el cambio de estatus', function () {
    $oc = OrdenCompra::factory()->pendienteFactura()->create();

    $oc->transitionTo(OrdenCompraEstatus::Cancelada);

    $actividad = Activity::where('subject_type', OrdenCompra::class)
        ->where('subject_id', $oc->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($actividad)->not->toBeNull();
    expect(data_get($actividad->attribute_changes, 'attributes.estatus'))->toBe('cancelada');
    expect(data_get($actividad->attribute_changes, 'old.estatus'))->toBe('pendiente_factura');
});

test('el causer se asocia al usuario autenticado', function () {
    $user = User::factory()->create();
    $oc = OrdenCompra::factory()->pendienteFactura()->create();

    $this->actingAs($user);
    $oc->transitionTo(OrdenCompraEstatus::Cancelada);

    $actividad = Activity::where('subject_type', OrdenCompra::class)
        ->where('subject_id', $oc->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($actividad->causer_id)->toBe($user->id);
});

test('logOnlyDirty no registra cambios en campos fuera de logOnly', function () {
    $oc = OrdenCompra::factory()->pendienteFactura()->create();

    $countAntes = Activity::where('subject_type', OrdenCompra::class)
        ->where('subject_id', $oc->id)
        ->count();

    // 'referencia' no esta en logOnly de OC
    $oc->update(['referencia' => 'nueva-ref']);

    $countDespues = Activity::where('subject_type', OrdenCompra::class)
        ->where('subject_id', $oc->id)
        ->count();

    expect($countDespues)->toBe($countAntes);
});

test('factura aprobarCostos registra activity con aprobada_costos=true', function () {
    $factura = Factura::factory()->create(['estatus' => FacturaEstatus::PendienteAprobacion->value]);

    $factura->update([
        'aprobada_costos' => true,
        'aprobada_costos_at' => now(),
    ]);

    $actividad = Activity::where('subject_type', Factura::class)
        ->where('subject_id', $factura->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($actividad)->not->toBeNull();
    expect(data_get($actividad->attribute_changes, 'attributes.aprobada_costos'))->toBeTrue();
});
