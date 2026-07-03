<?php

use App\Models\Rh\PermisoAusencia;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['rh.permisos-ausencia.ver', 'rh.permisos-ausencia.crear', 'rh.permisos-ausencia.editar', 'rh.permisos-ausencia.eliminar'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $this->user->givePermissionTo(['rh.permisos-ausencia.ver', 'rh.permisos-ausencia.crear', 'rh.permisos-ausencia.editar', 'rh.permisos-ausencia.eliminar']);
});

describe('admin rh permisos ausencia', function () {
    test('index page can be rendered', function () {
        PermisoAusencia::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.rh.permisos-ausencia.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/rh/permisos-ausencia/index')
            ->has('permisos.data', 3)
        );
    });

    test('permiso ausencia can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.permisos-ausencia.store'), [
                'nombres' => 'Juan',
                'apellidos' => 'Perez',
                'tipo' => 'vacaciones',
                'modalidad' => 'con_goce',
                'fecha_permiso' => '2026-03-15',
            ]);

        $response->assertRedirect(route('admin.rh.permisos-ausencia.index'));
        $this->assertDatabaseHas('rh_permisos_ausencia', [
            'nombres' => 'Juan',
            'apellidos' => 'Perez',
        ]);
    });

    test('permiso ausencia auto-generates folio', function () {
        $this->actingAs($this->user)
            ->post(route('admin.rh.permisos-ausencia.store'), [
                'nombres' => 'Juan',
                'apellidos' => 'Perez',
            ]);

        $permiso = PermisoAusencia::first();
        expect($permiso->folio)->toStartWith('PA-'.date('y').'-');
    });

    test('permiso ausencia can be updated', function () {
        $permiso = PermisoAusencia::factory()->create(['nombres' => 'Old']);

        $response = $this->actingAs($this->user)
            ->put(route('admin.rh.permisos-ausencia.update', $permiso), [
                'nombres' => 'Updated',
                'apellidos' => $permiso->apellidos,
            ]);

        $response->assertRedirect(route('admin.rh.permisos-ausencia.index'));
        $this->assertDatabaseHas('rh_permisos_ausencia', [
            'id' => $permiso->id,
            'nombres' => 'Updated',
        ]);
    });

    test('permiso ausencia can be deleted', function () {
        $permiso = PermisoAusencia::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.rh.permisos-ausencia.destroy', $permiso));

        $response->assertRedirect(route('admin.rh.permisos-ausencia.index'));
        $this->assertDatabaseMissing('rh_permisos_ausencia', ['id' => $permiso->id]);
    });

    test('validation requires nombres and apellidos', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.permisos-ausencia.store'), []);

        $response->assertSessionHasErrors(['nombres', 'apellidos']);
    });
});
