<?php

use App\Models\Costos\Requisicion;
use App\Models\Costos\SolicitudPago;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['costos.solicitudes-pago.ver', 'costos.solicitudes-pago.ver-todas'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
});

test('el reporte descarga un PDF', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['costos.solicitudes-pago.ver', 'costos.solicitudes-pago.ver-todas']);

    SolicitudPago::factory()->count(2)->create();
    Requisicion::factory()->count(3)->create();

    $this->actingAs($user)
        ->get(route('admin.costos.solicitudes-pago.reporte-pdf'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('sin permiso ver no puede generar el reporte', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.costos.solicitudes-pago.reporte-pdf'))
        ->assertForbidden();
});

test('sin ver-todas el reporte solo considera lo propio', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('costos.solicitudes-pago.ver');

    SolicitudPago::factory()->create(['solicitante_id' => $user->id]);
    SolicitudPago::factory()->create(); // de otro solicitante

    // No revienta y responde PDF aun filtrando por solicitante.
    $this->actingAs($user)
        ->get(route('admin.costos.solicitudes-pago.reporte-pdf'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
