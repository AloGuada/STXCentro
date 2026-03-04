<?php

use App\Models\Rh\Puesto;
use App\Models\Rh\Skill;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['rh.skills.ver', 'rh.skills.crear', 'rh.skills.editar', 'rh.skills.eliminar'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $this->user->givePermissionTo(['rh.skills.ver', 'rh.skills.crear', 'rh.skills.editar', 'rh.skills.eliminar']);
});

describe('admin rh skills', function () {
    test('index page can be rendered', function () {
        Skill::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.rh.skills.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/rh/skills/index')
            ->has('skills.data', 3)
        );
    });

    test('index supports search filter', function () {
        Skill::factory()->create(['nombre' => 'PHP']);
        Skill::factory()->create(['nombre' => 'Liderazgo']);

        $response = $this->actingAs($this->user)
            ->get(route('admin.rh.skills.index', ['search' => 'PHP']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('skills.data', 1)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.rh.skills.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/rh/skills/create')
        );
    });

    test('skill can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.skills.store'), [
                'nombre' => 'PHP',
                'tipo' => 'hard',
            ]);

        $response->assertRedirect(route('admin.rh.skills.index'));
        $this->assertDatabaseHas('rh_skills', [
            'nombre' => 'PHP',
            'tipo' => 'hard',
        ]);
    });

    test('edit page can be rendered', function () {
        $skill = Skill::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.rh.skills.edit', $skill));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/rh/skills/edit')
            ->has('skill')
        );
    });

    test('skill can be updated', function () {
        $skill = Skill::factory()->create(['nombre' => 'Old', 'tipo' => 'hard']);

        $response = $this->actingAs($this->user)
            ->put(route('admin.rh.skills.update', $skill), [
                'nombre' => 'Updated',
                'tipo' => 'soft',
            ]);

        $response->assertRedirect(route('admin.rh.skills.index'));
        $this->assertDatabaseHas('rh_skills', [
            'id' => $skill->id,
            'nombre' => 'Updated',
            'tipo' => 'soft',
        ]);
    });

    test('skill can be deleted when no puestos', function () {
        $skill = Skill::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.rh.skills.destroy', $skill));

        $response->assertRedirect(route('admin.rh.skills.index'));
        $this->assertDatabaseMissing('rh_skills', ['id' => $skill->id]);
    });

    test('skill cannot be deleted with puestos', function () {
        $skill = Skill::factory()->create();
        $puesto = Puesto::factory()->create();
        $puesto->skills()->attach($skill->id, ['nivel_requerido' => 'basico']);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.rh.skills.destroy', $skill));

        $response->assertSessionHasErrors(['delete']);
        $this->assertDatabaseHas('rh_skills', ['id' => $skill->id]);
    });

    test('validation requires nombre and tipo', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.skills.store'), []);

        $response->assertSessionHasErrors(['nombre', 'tipo']);
    });

    test('validation rejects invalid tipo', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.skills.store'), [
                'nombre' => 'Test',
                'tipo' => 'invalid',
            ]);

        $response->assertSessionHasErrors(['tipo']);
    });
});
