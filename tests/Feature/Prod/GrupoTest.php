<?php

use App\Models\Prod\EmpleadoGrupo;
use App\Models\Prod\Fabricado;
use App\Models\Prod\Grupo;
use App\Models\Prod\PagoExtra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin grupos', function () {
    test('index page can be rendered', function () {
        Grupo::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.grupos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/grupos/index')
            ->has('grupos.data', 3)
        );
    });

    test('grupo can be stored with empleados', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.grupos.store'), [
                'descripcion' => 'Grupo Alfa',
                'empleados' => [
                    ['nombre' => 'Juan', 'no_empleado' => '001'],
                    ['nombre' => 'Pedro', 'no_empleado' => '002'],
                ],
            ]);

        $response->assertRedirect();

        $grupo = Grupo::where('descripcion', 'Grupo Alfa')->first();
        expect($grupo)->not->toBeNull();
        expect($grupo->empleados)->toHaveCount(2);
    });

    test('grupo can be updated', function () {
        $grupo = Grupo::factory()->create(['descripcion' => 'Old']);

        $response = $this->actingAs($this->user)
            ->put(route('admin.prod.grupos.update', $grupo), [
                'descripcion' => 'Updated',
            ]);

        $response->assertRedirect(route('admin.prod.grupos.index'));

        $this->assertDatabaseHas('prod_grupos', [
            'id' => $grupo->id,
            'descripcion' => 'Updated',
        ]);
    });

    test('grupo can be deleted when no fabricados or pagos extra', function () {
        $grupo = Grupo::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.grupos.destroy', $grupo));

        $response->assertRedirect(route('admin.prod.grupos.index'));
        $this->assertDatabaseMissing('prod_grupos', ['id' => $grupo->id]);
    });

    test('grupo cannot be deleted with fabricados', function () {
        $grupo = Grupo::factory()->create();
        Fabricado::factory()->create(['dest_grupo_id' => $grupo->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.grupos.destroy', $grupo));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('prod_grupos', ['id' => $grupo->id]);
    });

    test('grupo cannot be deleted with pagos extra', function () {
        $grupo = Grupo::factory()->create();
        PagoExtra::factory()->create(['dest_grupo_id' => $grupo->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.grupos.destroy', $grupo));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('prod_grupos', ['id' => $grupo->id]);
    });

    test('empleado can be added to grupo', function () {
        $grupo = Grupo::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.grupos.empleados.store', $grupo), [
                'nombre' => 'Nuevo Empleado',
                'no_empleado' => '100',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('prod_empleados_grupo', [
            'grupo_id' => $grupo->id,
            'nombre' => 'Nuevo Empleado',
        ]);
    });

    test('empleado can be removed from grupo', function () {
        $grupo = Grupo::factory()->create();
        $empleado = EmpleadoGrupo::factory()->create(['grupo_id' => $grupo->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.grupos.empleados.destroy', [$grupo, $empleado]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('prod_empleados_grupo', ['id' => $empleado->id]);
    });
});
