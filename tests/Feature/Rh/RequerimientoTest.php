<?php

use App\Models\Rh\Puesto;
use App\Models\Rh\Requerimiento;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['rh.requerimientos.ver', 'rh.requerimientos.crear', 'rh.requerimientos.editar', 'rh.requerimientos.eliminar'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $this->user->givePermissionTo(['rh.requerimientos.ver', 'rh.requerimientos.crear', 'rh.requerimientos.editar', 'rh.requerimientos.eliminar']);
});

describe('admin rh requerimientos', function () {
    test('index page can be rendered', function () {
        Requerimiento::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.rh.requerimientos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/rh/requerimientos/index')
            ->has('requerimientos.data', 3)
        );
    });

    test('requerimiento can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.requerimientos.store'), [
                'descripcion' => 'Licencia de conducir',
                'valor' => 'Tipo B',
            ]);

        $response->assertRedirect(route('admin.rh.requerimientos.index'));
        $this->assertDatabaseHas('rh_requerimientos', [
            'descripcion' => 'Licencia de conducir',
        ]);
    });

    test('requerimiento can be updated', function () {
        $req = Requerimiento::factory()->create(['descripcion' => 'Old']);

        $response = $this->actingAs($this->user)
            ->put(route('admin.rh.requerimientos.update', $req), [
                'descripcion' => 'Updated',
            ]);

        $response->assertRedirect(route('admin.rh.requerimientos.index'));
        $this->assertDatabaseHas('rh_requerimientos', [
            'id' => $req->id,
            'descripcion' => 'Updated',
        ]);
    });

    test('requerimiento can be deleted when no puestos', function () {
        $req = Requerimiento::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.rh.requerimientos.destroy', $req));

        $response->assertRedirect(route('admin.rh.requerimientos.index'));
        $this->assertDatabaseMissing('rh_requerimientos', ['id' => $req->id]);
    });

    test('requerimiento cannot be deleted with puestos', function () {
        $req = Requerimiento::factory()->create();
        $puesto = Puesto::factory()->create();
        $puesto->requerimientos()->attach($req->id);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.rh.requerimientos.destroy', $req));

        $response->assertSessionHasErrors(['delete']);
        $this->assertDatabaseHas('rh_requerimientos', ['id' => $req->id]);
    });

    test('validation requires descripcion', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.requerimientos.store'), []);

        $response->assertSessionHasErrors(['descripcion']);
    });
});
