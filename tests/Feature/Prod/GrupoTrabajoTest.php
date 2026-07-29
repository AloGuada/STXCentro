<?php

use App\Models\Prod\CategoriaEmpleado;
use App\Models\Prod\GrupoEmpleado;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Registro;
use App\Models\Prod\Ubicacion;
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

    test('grupo trabajo can be stored with ubicaciones y empleados', function () {
        $ubicaciones = Ubicacion::factory()->count(2)->create();
        $categoria = CategoriaEmpleado::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.grupos-trabajo.store'), [
                'descripcion' => 'Grupo Alpha',
                'activo' => true,
                'ubicacion_ids' => $ubicaciones->pluck('id')->all(),
                'empleados' => [
                    ['nombre' => 'Juan', 'no_empleado' => 'E001', 'categoria_empleado_id' => $categoria->id],
                    ['nombre' => 'Pedro', 'no_empleado' => 'E002', 'categoria_empleado_id' => $categoria->id],
                ],
            ]);

        $grupo = GrupoTrabajo::first();
        $response->assertRedirect(route('admin.prod.grupos-trabajo.edit', $grupo));

        $this->assertDatabaseHas('prod_grupos_trabajo', ['descripcion' => 'Grupo Alpha']);

        expect(GrupoEmpleado::where('grupo_trabajo_id', $grupo->id)->count())->toBe(2)
            ->and($grupo->ubicaciones)->toHaveCount(2)
            ->and($grupo->empleados()->first()->categoria_empleado_id)->toBe($categoria->id);
    });

    test('grupo trabajo can be updated y resincroniza ubicaciones', function () {
        $grupo = GrupoTrabajo::factory()->create();
        $vieja = Ubicacion::factory()->create();
        $nueva = Ubicacion::factory()->create();
        $grupo->ubicaciones()->sync([$vieja->id]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.prod.grupos-trabajo.update', $grupo), [
                'descripcion' => 'Updated',
                'activo' => false,
                'ubicacion_ids' => [$nueva->id],
            ]);

        $response->assertRedirect(route('admin.prod.grupos-trabajo.index'));

        $this->assertDatabaseHas('prod_grupos_trabajo', [
            'id' => $grupo->id,
            'descripcion' => 'Updated',
            'activo' => false,
        ]);

        expect($grupo->fresh()->ubicaciones->pluck('id')->all())->toBe([$nueva->id]);
    });

    test('un grupo puede usar varias ubicaciones', function () {
        $grupo = GrupoTrabajo::factory()->create();
        $ubicaciones = Ubicacion::factory()->count(3)->create();

        $this->actingAs($this->user)
            ->put(route('admin.prod.grupos-trabajo.update', $grupo), [
                'descripcion' => $grupo->descripcion,
                'ubicacion_ids' => $ubicaciones->pluck('id')->all(),
            ])
            ->assertSessionHasNoErrors();

        expect($grupo->fresh()->ubicaciones)->toHaveCount(3);
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
                'categoria_empleado_id' => CategoriaEmpleado::factory()->create()->id,
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
