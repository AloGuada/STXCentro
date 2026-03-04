<?php

use App\Models\Departamento;
use App\Models\Rh\Puesto;
use App\Models\Rh\Requerimiento;
use App\Models\Rh\Skill;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['rh.puestos.ver', 'rh.puestos.crear', 'rh.puestos.editar', 'rh.puestos.eliminar'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $this->user->givePermissionTo(['rh.puestos.ver', 'rh.puestos.crear', 'rh.puestos.editar', 'rh.puestos.eliminar']);
});

describe('admin rh puestos', function () {
    test('index page can be rendered', function () {
        Puesto::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.rh.puestos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/rh/puestos/index')
            ->has('puestos.data', 3)
        );
    });

    test('puesto can be stored', function () {
        $departamento = Departamento::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.puestos.store'), [
                'departamento_id' => $departamento->id,
                'nombre' => 'Desarrollador Senior',
                'descripcion' => 'Desarrollo de software',
                'codigo' => 'DS001',
            ]);

        $response->assertRedirect(route('admin.rh.puestos.index'));
        $this->assertDatabaseHas('rh_puestos', [
            'nombre' => 'Desarrollador Senior',
        ]);
    });

    test('puesto can be updated', function () {
        $puesto = Puesto::factory()->create(['nombre' => 'Old']);
        $departamento = Departamento::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.rh.puestos.update', $puesto), [
                'departamento_id' => $departamento->id,
                'nombre' => 'Updated',
            ]);

        $response->assertRedirect(route('admin.rh.puestos.index'));
        $this->assertDatabaseHas('rh_puestos', [
            'id' => $puesto->id,
            'nombre' => 'Updated',
        ]);
    });

    test('existing skill can be added to puesto', function () {
        $puesto = Puesto::factory()->create();
        $skill = Skill::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.puestos.skills.add', $puesto), [
                'skill_id' => $skill->id,
                'nivel_requerido' => 'basico',
            ]);

        $response->assertRedirect();
        expect($puesto->fresh()->skills)->toHaveCount(1);
    });

    test('new skill can be created and added to puesto', function () {
        $puesto = Puesto::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.puestos.skills.add', $puesto), [
                'nombre' => 'Laravel',
                'tipo' => 'hard',
                'nivel_requerido' => 'avanzado',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rh_skills', ['nombre' => 'Laravel']);
        expect($puesto->fresh()->skills)->toHaveCount(1);
    });

    test('skill can be removed from puesto', function () {
        $puesto = Puesto::factory()->create();
        $skill = Skill::factory()->create();
        $puesto->skills()->attach($skill->id, ['nivel_requerido' => 'basico']);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.rh.puestos.skills.remove', [$puesto, $skill]));

        $response->assertRedirect();
        expect($puesto->fresh()->skills)->toHaveCount(0);
    });

    test('existing requerimiento can be added to puesto', function () {
        $puesto = Puesto::factory()->create();
        $req = Requerimiento::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.puestos.requerimientos.add', $puesto), [
                'requerimiento_id' => $req->id,
            ]);

        $response->assertRedirect();
        expect($puesto->fresh()->requerimientos)->toHaveCount(1);
    });

    test('new requerimiento can be created and added to puesto', function () {
        $puesto = Puesto::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.puestos.requerimientos.add', $puesto), [
                'descripcion' => 'Licencia de conducir',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rh_requerimientos', ['descripcion' => 'Licencia de conducir']);
        expect($puesto->fresh()->requerimientos)->toHaveCount(1);
    });

    test('requerimiento can be removed from puesto', function () {
        $puesto = Puesto::factory()->create();
        $req = Requerimiento::factory()->create();
        $puesto->requerimientos()->attach($req->id);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.rh.puestos.requerimientos.remove', [$puesto, $req]));

        $response->assertRedirect();
        expect($puesto->fresh()->requerimientos)->toHaveCount(0);
    });

    test('puesto actividad can be added', function () {
        $puesto = Puesto::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.puestos.actividades.store', $puesto), [
                'descripcion' => 'Revisar codigo',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rh_actividades', [
            'puesto_id' => $puesto->id,
            'descripcion' => 'Revisar codigo',
        ]);
    });

    test('validation requires nombre and departamento', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.puestos.store'), []);

        $response->assertSessionHasErrors(['nombre', 'departamento_id']);
    });
});
