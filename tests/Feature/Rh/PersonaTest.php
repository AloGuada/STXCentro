<?php

use App\Models\Rh\Persona;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['rh.personas.ver', 'rh.personas.crear', 'rh.personas.editar', 'rh.personas.eliminar'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $this->user->givePermissionTo(['rh.personas.ver', 'rh.personas.crear', 'rh.personas.editar', 'rh.personas.eliminar']);
});

describe('admin rh personas', function () {
    test('index page can be rendered', function () {
        Persona::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.rh.personas.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/rh/personas/index')
            ->has('personas.data', 3)
        );
    });

    test('persona can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.personas.store'), [
                'nombre' => 'Juan',
                'apellido' => 'Perez',
                'email' => 'juan@test.com',
            ]);

        $response->assertRedirect(route('admin.rh.personas.index'));
        $this->assertDatabaseHas('rh_personas', [
            'nombre' => 'Juan',
            'apellido' => 'Perez',
        ]);
    });

    test('persona show page can be rendered', function () {
        $persona = Persona::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.rh.personas.show', $persona));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/rh/personas/show')
            ->has('persona')
        );
    });

    test('persona can be updated', function () {
        $persona = Persona::factory()->create(['nombre' => 'Old']);

        $response = $this->actingAs($this->user)
            ->put(route('admin.rh.personas.update', $persona), [
                'nombre' => 'Updated',
                'apellido' => $persona->apellido,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rh_personas', [
            'id' => $persona->id,
            'nombre' => 'Updated',
        ]);
    });

    test('persona can be deleted when no periodos', function () {
        $persona = Persona::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.rh.personas.destroy', $persona));

        $response->assertRedirect(route('admin.rh.personas.index'));
        $this->assertDatabaseMissing('rh_personas', ['id' => $persona->id]);
    });

    test('persona datos extra can be saved', function () {
        $persona = Persona::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.rh.personas.update', $persona), [
                'nombre' => $persona->nombre,
                'apellido' => $persona->apellido,
                'curp' => 'GARC850101HYNRRL09',
                'rfc' => 'GARC850101AB',
                'imss' => '12345678901',
                'estado_civil' => 'soltero',
                'domicilio' => 'Calle 10 x 15',
                'cp' => '97000',
                'localidad' => 'Merida',
                'cuenta_banco' => '1234567890',
                'c_infonavit' => 'no',
                'c_fonacot' => 'no',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rh_personas', [
            'id' => $persona->id,
            'curp' => 'GARC850101HYNRRL09',
            'rfc' => 'GARC850101AB',
            'estado_civil' => 'soltero',
            'cp' => '97000',
        ]);
    });

    test('validation requires nombre and apellido', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.personas.store'), []);

        $response->assertSessionHasErrors(['nombre', 'apellido']);
    });
});
