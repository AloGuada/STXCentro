<?php

use App\Models\Costos\Requisicion;
use App\Models\Costos\SolicitudPago;
use App\Models\Departamento;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'costos.requisiciones.ver', 'costos.requisiciones.ver-todas',
        'costos.solicitudes-pago.ver', 'costos.solicitudes-pago.ver-todas',
    ] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $this->depto = Departamento::factory()->create();
});

test('usuario común solo ve sus propias requisiciones', function () {
    $comun = User::factory()->create();
    $comun->givePermissionTo('costos.requisiciones.ver');
    $otro = User::factory()->create();

    Requisicion::factory()->create(['solicitante_id' => $comun->id, 'departamento_id' => $this->depto->id]);
    Requisicion::factory()->count(2)->create(['solicitante_id' => $otro->id, 'departamento_id' => $this->depto->id]);

    $this->actingAs($comun)
        ->get('/admin/costos/requisiciones')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('requisiciones.data', 1));
});

test('operador con ver-todas ve todas las requisiciones', function () {
    $op = User::factory()->create();
    $op->givePermissionTo(['costos.requisiciones.ver', 'costos.requisiciones.ver-todas']);
    $otro = User::factory()->create();

    Requisicion::factory()->count(3)->create(['solicitante_id' => $otro->id, 'departamento_id' => $this->depto->id]);

    $this->actingAs($op)
        ->get('/admin/costos/requisiciones')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('requisiciones.data', 3));
});

test('usuario común solo ve sus propias solicitudes de pago', function () {
    $comun = User::factory()->create();
    $comun->givePermissionTo('costos.solicitudes-pago.ver');
    $otro = User::factory()->create();

    SolicitudPago::factory()->create(['solicitante_id' => $comun->id, 'departamento_id' => $this->depto->id]);
    SolicitudPago::factory()->count(2)->create(['solicitante_id' => $otro->id, 'departamento_id' => $this->depto->id]);

    $this->actingAs($comun)
        ->get('/admin/costos/solicitudes-pago')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('solicitudes.data', 1));
});

test('operador con ver-todas ve todas las solicitudes de pago', function () {
    $op = User::factory()->create();
    $op->givePermissionTo(['costos.solicitudes-pago.ver', 'costos.solicitudes-pago.ver-todas']);
    $otro = User::factory()->create();

    SolicitudPago::factory()->count(3)->create(['solicitante_id' => $otro->id, 'departamento_id' => $this->depto->id]);

    $this->actingAs($op)
        ->get('/admin/costos/solicitudes-pago')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('solicitudes.data', 3));
});
