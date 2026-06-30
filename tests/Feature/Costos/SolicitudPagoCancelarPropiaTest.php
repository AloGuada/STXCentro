<?php

use App\Models\Costos\SolicitudPago;
use App\Models\Departamento;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['costos.solicitudes-pago.editar', 'costos.solicitudes-pago.cancelar-propia'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $this->depto = Departamento::factory()->create();
});

test('el solicitante con permiso puede cancelar su propia solicitud', function () {
    $duenio = User::factory()->create();
    $duenio->givePermissionTo('costos.solicitudes-pago.cancelar-propia');

    $solicitud = SolicitudPago::factory()->create([
        'solicitante_id' => $duenio->id,
        'departamento_id' => $this->depto->id,
        'estatus' => 'pendiente_firma',
    ]);

    $this->actingAs($duenio)
        ->post("/admin/costos/solicitudes-pago/{$solicitud->id}/cancelar", ['motivo' => 'Ya no se requiere'])
        ->assertRedirect();

    expect($solicitud->fresh()->estatus->value)->toBe('cancelada');
});

test('no puede cancelar la solicitud de otro aunque tenga el permiso propio', function () {
    $usuario = User::factory()->create();
    $usuario->givePermissionTo('costos.solicitudes-pago.cancelar-propia');
    $otro = User::factory()->create();

    $solicitud = SolicitudPago::factory()->create([
        'solicitante_id' => $otro->id,
        'departamento_id' => $this->depto->id,
        'estatus' => 'pendiente_firma',
    ]);

    $this->actingAs($usuario)
        ->post("/admin/costos/solicitudes-pago/{$solicitud->id}/cancelar", ['motivo' => 'Cancelacion solicitada'])
        ->assertForbidden();

    expect($solicitud->fresh()->estatus->value)->toBe('pendiente_firma');
});

test('el operador con editar puede cancelar cualquier solicitud', function () {
    $operador = User::factory()->create();
    $operador->givePermissionTo('costos.solicitudes-pago.editar');
    $otro = User::factory()->create();

    $solicitud = SolicitudPago::factory()->create([
        'solicitante_id' => $otro->id,
        'departamento_id' => $this->depto->id,
        'estatus' => 'pendiente_firma',
    ]);

    $this->actingAs($operador)
        ->post("/admin/costos/solicitudes-pago/{$solicitud->id}/cancelar", ['motivo' => 'Cancelacion solicitada'])
        ->assertRedirect();

    expect($solicitud->fresh()->estatus->value)->toBe('cancelada');
});

test('sin permiso propio ni de operador no puede cancelar', function () {
    $duenio = User::factory()->create();

    $solicitud = SolicitudPago::factory()->create([
        'solicitante_id' => $duenio->id,
        'departamento_id' => $this->depto->id,
        'estatus' => 'pendiente_firma',
    ]);

    $this->actingAs($duenio)
        ->post("/admin/costos/solicitudes-pago/{$solicitud->id}/cancelar", ['motivo' => 'Cancelacion solicitada'])
        ->assertForbidden();
});
