<?php

use App\Models\Rh\Persona;
use App\Models\Rh\Puesto;
use App\Models\Rh\Requisicion;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['rh.requisiciones.ver', 'rh.requisiciones.crear', 'rh.requisiciones.editar', 'rh.requisiciones.eliminar', 'rh.candidaturas.ver', 'rh.candidaturas.crear'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $this->user->givePermissionTo(['rh.requisiciones.ver', 'rh.requisiciones.crear', 'rh.requisiciones.editar', 'rh.requisiciones.eliminar', 'rh.candidaturas.ver', 'rh.candidaturas.crear']);
});

describe('admin rh requisiciones', function () {
    test('index page can be rendered', function () {
        Requisicion::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.rh.requisiciones.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/rh/requisiciones/index')
            ->has('requisiciones.data', 3)
        );
    });

    test('requisicion can be stored', function () {
        $puesto = Puesto::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.requisiciones.store'), [
                'puesto_id' => $puesto->id,
                'cantidad' => 2,
                'tipo_requisicion' => 'nueva',
                'tipo_contrato_generado' => 'planta',
                'justificacion' => 'Crecimiento del equipo',
            ]);

        $response->assertRedirect(route('admin.rh.requisiciones.index'));
        $this->assertDatabaseHas('rh_requisiciones', [
            'puesto_id' => $puesto->id,
            'cantidad' => 2,
            'tipo_contrato_generado' => 'planta',
        ]);
    });

    test('requisicion auto-generates folio', function () {
        $puesto = Puesto::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.rh.requisiciones.store'), [
                'puesto_id' => $puesto->id,
                'cantidad' => 1,
                'tipo_requisicion' => 'nueva',
                'tipo_contrato_generado' => 'obra',
            ]);

        $req = Requisicion::first();
        expect($req->folio)->toStartWith('REQ-'.date('Y').'-');
    });

    test('show page can be rendered', function () {
        $requisicion = Requisicion::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.rh.requisiciones.show', $requisicion));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/rh/requisiciones/show')
            ->has('requisicion')
        );
    });

    test('candidatura can be added to requisicion', function () {
        $requisicion = Requisicion::factory()->create();
        $persona = Persona::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.requisiciones.candidaturas.store', $requisicion), [
                'persona_id' => $persona->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rh_candidaturas', [
            'requisicion_id' => $requisicion->id,
            'persona_id' => $persona->id,
        ]);
    });
});
