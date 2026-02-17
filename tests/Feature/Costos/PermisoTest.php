<?php

use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\Permiso;
use App\Models\Departamento;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin costos permisos', function () {
    test('index page can be rendered', function () {
        Permiso::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.permisos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/permisos/index')
            ->has('permisos.data', 3)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.permisos.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/permisos/create')
        );
    });

    test('permiso can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.permisos.store'), [
                'descripcion' => 'Jefe Depto',
                'nivel' => 1,
            ]);

        $response->assertRedirect(route('admin.costos.permisos.index'));
        $this->assertDatabaseHas('costos_permisos', [
            'descripcion' => 'Jefe Depto',
            'nivel' => 1,
        ]);
    });

    test('show page loads departamentos and usuarios', function () {
        $permiso = Permiso::factory()->create();
        Departamento::factory()->count(2)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.permisos.show', $permiso));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/permisos/show')
            ->has('permiso')
            ->has('departamentos')
            ->has('usuarios')
            ->has('asignaciones')
        );
    });

    test('edit page can be rendered', function () {
        $permiso = Permiso::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.permisos.edit', $permiso));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/permisos/edit')
            ->has('permiso')
        );
    });

    test('permiso can be updated', function () {
        $permiso = Permiso::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.costos.permisos.update', $permiso), [
                'descripcion' => 'Gerente',
                'nivel' => 2,
            ]);

        $response->assertRedirect(route('admin.costos.permisos.index'));
        $this->assertDatabaseHas('costos_permisos', [
            'id' => $permiso->id,
            'descripcion' => 'Gerente',
            'nivel' => 2,
        ]);
    });

    test('permiso can be deleted', function () {
        $permiso = Permiso::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.costos.permisos.destroy', $permiso));

        $response->assertRedirect(route('admin.costos.permisos.index'));
        $this->assertDatabaseMissing('costos_permisos', ['id' => $permiso->id]);
    });

    test('syncDepartamentos creates assignments', function () {
        $permiso = Permiso::factory()->create();
        $departamento = Departamento::factory()->create();
        $aprobador = User::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.permisos.sync-departamentos', $permiso), [
                'asignaciones' => [
                    ['departamento_id' => $departamento->id, 'aprobador_id' => $aprobador->id],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('costos_aprobacion_departamento', [
            'permiso_id' => $permiso->id,
            'departamento_id' => $departamento->id,
            'aprobador_id' => $aprobador->id,
        ]);
    });

    test('syncDepartamentos replaces existing assignments', function () {
        $permiso = Permiso::factory()->create();
        $departamento = Departamento::factory()->create();
        $oldAprobador = User::factory()->create();
        $newAprobador = User::factory()->create();

        AprobacionDepartamento::factory()->create([
            'permiso_id' => $permiso->id,
            'departamento_id' => $departamento->id,
            'aprobador_id' => $oldAprobador->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.permisos.sync-departamentos', $permiso), [
                'asignaciones' => [
                    ['departamento_id' => $departamento->id, 'aprobador_id' => $newAprobador->id],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('costos_aprobacion_departamento', [
            'permiso_id' => $permiso->id,
            'departamento_id' => $departamento->id,
            'aprobador_id' => $newAprobador->id,
        ]);
        $this->assertDatabaseMissing('costos_aprobacion_departamento', [
            'aprobador_id' => $oldAprobador->id,
        ]);
    });

    test('syncDepartamentos ignores null aprobador_id', function () {
        $permiso = Permiso::factory()->create();
        $departamento = Departamento::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.permisos.sync-departamentos', $permiso), [
                'asignaciones' => [
                    ['departamento_id' => $departamento->id, 'aprobador_id' => null],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('costos_aprobacion_departamento', [
            'permiso_id' => $permiso->id,
            'departamento_id' => $departamento->id,
        ]);
    });

    test('validation requires descripcion and nivel', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.permisos.store'), []);

        $response->assertSessionHasErrors(['descripcion', 'nivel']);
    });
});
