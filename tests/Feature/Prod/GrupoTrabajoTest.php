<?php

use App\Models\Prod\GrupoEmpleado;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Registro;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin grupos trabajo', function () {
    test('index page can be rendered', function () {
        GrupoTrabajo::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.grupos-trabajo.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/grupos-trabajo/index')
            ->has('grupos.data', 3)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.grupos-trabajo.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/grupos-trabajo/create')
        );
    });

    test('grupo trabajo can be stored with empleados', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.grupos-trabajo.store'), [
                'descripcion' => 'Grupo Alpha',
                'linea' => 1,
                'modulo' => 2,
                'activo' => true,
                'empleados' => [
                    ['nombre' => 'Juan', 'no_empleado' => 'E001', 'porcentaje' => 50],
                    ['nombre' => 'Pedro', 'no_empleado' => 'E002', 'porcentaje' => 50],
                ],
            ]);

        $grupo = GrupoTrabajo::first();
        $response->assertRedirect(route('admin.prod.grupos-trabajo.edit', $grupo));

        $this->assertDatabaseHas('prod_grupos_trabajo', [
            'descripcion' => 'Grupo Alpha',
            'linea' => 1,
            'modulo' => 2,
        ]);

        expect(GrupoEmpleado::where('grupo_trabajo_id', $grupo->id)->count())->toBe(2);
    });

    test('grupo trabajo can be updated', function () {
        $grupo = GrupoTrabajo::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.prod.grupos-trabajo.update', $grupo), [
                'descripcion' => 'Updated',
                'linea' => 5,
                'modulo' => 3,
                'activo' => false,
            ]);

        $response->assertRedirect(route('admin.prod.grupos-trabajo.index'));

        $this->assertDatabaseHas('prod_grupos_trabajo', [
            'id' => $grupo->id,
            'descripcion' => 'Updated',
            'linea' => 5,
            'activo' => false,
        ]);
    });

    test('grupo trabajo can be deleted', function () {
        $grupo = GrupoTrabajo::factory()->create();
        GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $grupo->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.grupos-trabajo.destroy', $grupo));

        $response->assertRedirect(route('admin.prod.grupos-trabajo.index'));
        $this->assertDatabaseMissing('prod_grupos_trabajo', ['id' => $grupo->id]);
        $this->assertDatabaseMissing('prod_grupo_empleados', ['grupo_trabajo_id' => $grupo->id]);
    });

    test('grupo trabajo cannot be deleted with registros', function () {
        $grupo = GrupoTrabajo::factory()->create();
        Registro::factory()->create(['grupo_trabajo_id' => $grupo->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.grupos-trabajo.destroy', $grupo));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('prod_grupos_trabajo', ['id' => $grupo->id]);
    });

    test('empleado can be added to grupo', function () {
        $grupo = GrupoTrabajo::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.grupos-trabajo.empleados.store', $grupo), [
                'nombre' => 'Nuevo Empleado',
                'no_empleado' => 'E099',
                'porcentaje' => 100,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('prod_grupo_empleados', [
            'grupo_trabajo_id' => $grupo->id,
            'nombre' => 'Nuevo Empleado',
            'no_empleado' => 'E099',
        ]);
    });

    test('empleado can be removed from grupo', function () {
        $grupo = GrupoTrabajo::factory()->create();
        $empleado = GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $grupo->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.grupos-trabajo.empleados.destroy', [$grupo, $empleado]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('prod_grupo_empleados', ['id' => $empleado->id]);
    });
});
