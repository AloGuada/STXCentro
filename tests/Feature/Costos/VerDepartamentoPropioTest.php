<?php

use App\Models\Costos\Requisicion;
use App\Models\Costos\SolicitudPago;
use App\Models\Departamento;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'costos.requisiciones.ver',
        'costos.requisiciones.ver-departamento-propio',
        'costos.solicitudes-pago.ver',
        'costos.solicitudes-pago.ver-departamento-propio',
    ] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->deptoA = Departamento::factory()->create();
    $this->deptoB = Departamento::factory()->create();
    $this->colegaA = User::factory()->create(['departamento_id' => $this->deptoA->id]);
    $this->ajenoB = User::factory()->create(['departamento_id' => $this->deptoB->id]);
});

test('con el permiso, el usuario ve lo creado por colegas de su departamento', function () {
    $usuario = User::factory()->create(['departamento_id' => $this->deptoA->id]);
    $usuario->givePermissionTo(['costos.requisiciones.ver', 'costos.requisiciones.ver-departamento-propio']);

    // Creada por un colega del mismo departamento; el departamento del registro es otro
    // a propósito, para probar que la visibilidad depende del creador, no del registro.
    $propia = Requisicion::factory()->create([
        'solicitante_id' => $this->colegaA->id,
        'departamento_id' => $this->deptoB->id,
    ]);
    Requisicion::factory()->create([
        'solicitante_id' => $this->ajenoB->id,
        'departamento_id' => $this->deptoA->id,
    ]);

    $this->actingAs($usuario)
        ->get(route('admin.costos.requisiciones.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('requisiciones.data', 1)
            ->where('requisiciones.data.0.folio', $propia->folio)
        );
});

test('sin el permiso, el usuario NO ve lo creado por colegas de su departamento', function () {
    $usuario = User::factory()->create(['departamento_id' => $this->deptoA->id]);
    $usuario->givePermissionTo(['costos.requisiciones.ver']);

    Requisicion::factory()->create(['solicitante_id' => $this->colegaA->id]);

    $this->actingAs($usuario)
        ->get(route('admin.costos.requisiciones.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('requisiciones.data', 0));
});

test('con el permiso pero sin departamento, el usuario solo ve las propias', function () {
    $usuario = User::factory()->create(['departamento_id' => null]);
    $usuario->givePermissionTo(['costos.requisiciones.ver', 'costos.requisiciones.ver-departamento-propio']);

    Requisicion::factory()->create(['solicitante_id' => $this->colegaA->id]);
    $miPropia = Requisicion::factory()->create(['solicitante_id' => $usuario->id]);

    $this->actingAs($usuario)
        ->get(route('admin.costos.requisiciones.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('requisiciones.data', 1)
            ->where('requisiciones.data.0.folio', $miPropia->folio)
        );
});

test('con el permiso, el usuario ve las solicitudes de pago de colegas de su departamento', function () {
    $usuario = User::factory()->create(['departamento_id' => $this->deptoA->id]);
    $usuario->givePermissionTo(['costos.solicitudes-pago.ver', 'costos.solicitudes-pago.ver-departamento-propio']);

    $propia = SolicitudPago::factory()->create([
        'solicitante_id' => $this->colegaA->id,
        'departamento_id' => $this->deptoB->id,
    ]);
    SolicitudPago::factory()->create([
        'solicitante_id' => $this->ajenoB->id,
        'departamento_id' => $this->deptoA->id,
    ]);

    $this->actingAs($usuario)
        ->get(route('admin.costos.solicitudes-pago.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('solicitudes.data', 1)
            ->where('solicitudes.data.0.folio', $propia->folio)
        );
});

test('sin el permiso, el usuario NO ve las solicitudes de pago de colegas de su departamento', function () {
    $usuario = User::factory()->create(['departamento_id' => $this->deptoA->id]);
    $usuario->givePermissionTo(['costos.solicitudes-pago.ver']);

    SolicitudPago::factory()->create(['solicitante_id' => $this->colegaA->id]);

    $this->actingAs($usuario)
        ->get(route('admin.costos.solicitudes-pago.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('solicitudes.data', 0));
});
