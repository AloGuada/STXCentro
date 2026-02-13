<?php

use App\Models\Costos\AprobacionDepartamento;
use App\Models\Departamento;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin costos aprobaciones departamento', function () {
    test('index page can be rendered', function () {
        AprobacionDepartamento::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.aprobaciones-departamento.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/aprobaciones-departamento/index')
            ->has('aprobaciones.data', 3)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.aprobaciones-departamento.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/aprobaciones-departamento/create')
            ->has('departamentos')
            ->has('usuarios')
        );
    });

    test('aprobacion can be stored', function () {
        $departamento = Departamento::factory()->create();
        $aprobador = User::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.aprobaciones-departamento.store'), [
                'departamento_id' => $departamento->id,
                'nivel' => 1,
                'nombre_nivel' => 'Jefe Depto',
                'aprobador_id' => $aprobador->id,
                'activo' => true,
            ]);

        $response->assertRedirect(route('admin.costos.aprobaciones-departamento.index'));
        $this->assertDatabaseHas('costos_aprobacion_departamento', [
            'departamento_id' => $departamento->id,
            'nivel' => 1,
            'nombre_nivel' => 'Jefe Depto',
        ]);
    });

    test('edit page can be rendered', function () {
        $aprobacion = AprobacionDepartamento::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.aprobaciones-departamento.edit', $aprobacion));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/aprobaciones-departamento/edit')
            ->has('aprobacion')
        );
    });

    test('aprobacion can be updated', function () {
        $aprobacion = AprobacionDepartamento::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.costos.aprobaciones-departamento.update', $aprobacion), [
                'departamento_id' => $aprobacion->departamento_id,
                'nivel' => 2,
                'nombre_nivel' => 'Gerente',
                'activo' => true,
            ]);

        $response->assertRedirect(route('admin.costos.aprobaciones-departamento.index'));
        $this->assertDatabaseHas('costos_aprobacion_departamento', [
            'id' => $aprobacion->id,
            'nivel' => 2,
            'nombre_nivel' => 'Gerente',
        ]);
    });

    test('aprobacion can be deleted', function () {
        $aprobacion = AprobacionDepartamento::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.costos.aprobaciones-departamento.destroy', $aprobacion));

        $response->assertRedirect(route('admin.costos.aprobaciones-departamento.index'));
        $this->assertDatabaseMissing('costos_aprobacion_departamento', ['id' => $aprobacion->id]);
    });

    test('validation requires departamento and nivel', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.aprobaciones-departamento.store'), []);

        $response->assertSessionHasErrors(['departamento_id', 'nivel', 'nombre_nivel']);
    });
});
