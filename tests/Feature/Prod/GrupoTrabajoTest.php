<?php

use App\Models\Prod\CategoriaEmpleado;
use App\Models\Prod\GrupoEmpleado;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Registro;
use App\Models\Prod\Ubicacion;
use App\Models\Rh\PeriodoLaboral;
use App\Models\Rh\Persona;
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
        $juan = Persona::factory()->create(['nombre' => 'Juan', 'apellido' => 'Perez']);
        $pedro = Persona::factory()->create(['nombre' => 'Pedro', 'apellido' => 'Lopez']);

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.grupos-trabajo.store'), [
                'descripcion' => 'Grupo Alpha',
                'activo' => true,
                'ubicacion_ids' => $ubicaciones->pluck('id')->all(),
                'empleados' => [
                    ['persona_id' => $juan->id, 'categoria_empleado_id' => $categoria->id],
                    ['persona_id' => $pedro->id, 'categoria_empleado_id' => $categoria->id],
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
        $persona = Persona::factory()->create(['nombre' => 'Nuevo', 'apellido' => 'Empleado']);
        PeriodoLaboral::factory()->create([
            'persona_id' => $persona->id,
            'estado' => 'activo',
            'numero_empleado' => 'E099',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.grupos-trabajo.empleados.store', $grupo), [
                'persona_id' => $persona->id,
                'categoria_empleado_id' => CategoriaEmpleado::factory()->create()->id,
            ]);

        $response->assertRedirect();

        // El nombre y el número no se teclean: se copian de RH.
        $this->assertDatabaseHas('prod_grupo_empleados', [
            'grupo_trabajo_id' => $grupo->id,
            'persona_id' => $persona->id,
            'nombre' => 'Nuevo Empleado',
            'no_empleado' => 'E099',
        ]);
    });

    test('se puede dar de alta una persona nueva desde el grupo', function () {
        $grupo = GrupoTrabajo::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.prod.grupos-trabajo.empleados.store', $grupo), [
                'persona_nueva' => ['nombre' => 'Eventual', 'apellido' => 'Sin Contrato'],
            ])
            ->assertSessionHasNoErrors();

        $persona = Persona::where('nombre', 'Eventual')->firstOrFail();

        // Nace sin periodo laboral: es de RH, pero todavía no está contratada.
        expect($persona->periodoVigente)->toBeNull();

        $this->assertDatabaseHas('prod_grupo_empleados', [
            'grupo_trabajo_id' => $grupo->id,
            'persona_id' => $persona->id,
            'nombre' => 'Eventual Sin Contrato',
            'no_empleado' => null,
        ]);
    });

    test('el integrante exige persona elegida o nueva', function () {
        $grupo = GrupoTrabajo::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.prod.grupos-trabajo.empleados.store', $grupo), [
                'categoria_empleado_id' => CategoriaEmpleado::factory()->create()->id,
            ])
            ->assertSessionHasErrors('persona_id');

        expect(GrupoEmpleado::count())->toBe(0);
    });

    test('el numero de empleado sale del periodo vigente, no de uno dado de baja', function () {
        $grupo = GrupoTrabajo::factory()->create();
        $persona = Persona::factory()->create();
        PeriodoLaboral::factory()->create([
            'persona_id' => $persona->id,
            'estado' => 'baja',
            'numero_empleado' => 'VIEJO',
            'fecha_inicio' => '2020-01-01',
        ]);
        PeriodoLaboral::factory()->create([
            'persona_id' => $persona->id,
            'estado' => 'activo',
            'numero_empleado' => 'VIGENTE',
            'fecha_inicio' => '2026-01-01',
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.prod.grupos-trabajo.empleados.store', $grupo), ['persona_id' => $persona->id]);

        expect(GrupoEmpleado::first()->no_empleado)->toBe('VIGENTE');
    });

    test('una persona no puede estar en dos grupos', function () {
        $persona = Persona::factory()->create();
        GrupoEmpleado::factory()->create([
            'grupo_trabajo_id' => GrupoTrabajo::factory()->create()->id,
            'persona_id' => $persona->id,
        ]);

        $otro = GrupoTrabajo::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.prod.grupos-trabajo.empleados.store', $otro), ['persona_id' => $persona->id])
            ->assertSessionHasErrors('persona_id');

        expect(GrupoEmpleado::where('grupo_trabajo_id', $otro->id)->count())->toBe(0);
    });

    test('no se puede repetir a la misma persona dentro del alta del grupo', function () {
        $persona = Persona::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.prod.grupos-trabajo.store'), [
                'descripcion' => 'Grupo Repetido',
                'empleados' => [
                    ['persona_id' => $persona->id],
                    ['persona_id' => $persona->id],
                ],
            ])
            ->assertSessionHasErrors('empleados.1.persona_id');

        expect(GrupoTrabajo::where('descripcion', 'Grupo Repetido')->exists())->toBeFalse();
    });

    test('el buscador no ofrece a quien ya esta en un grupo', function () {
        $libre = Persona::factory()->create(['nombre' => 'Libre', 'apellido' => 'Sin Grupo']);
        $ocupada = Persona::factory()->create(['nombre' => 'Ocupada', 'apellido' => 'Con Grupo']);
        GrupoEmpleado::factory()->create([
            'grupo_trabajo_id' => GrupoTrabajo::factory()->create()->id,
            'persona_id' => $ocupada->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.prod.grupos-trabajo.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('personas', fn ($personas) => collect($personas)->pluck('id')->all() === [$libre->id])
            );
    });

    test('la pantalla de edicion dice si el integrante tiene contrato vigente', function () {
        $grupo = GrupoTrabajo::factory()->create();
        $persona = Persona::factory()->create();
        GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $grupo->id, 'persona_id' => $persona->id]);

        $this->actingAs($this->user)
            ->get(route('admin.prod.grupos-trabajo.edit', $grupo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/prod/grupos-trabajo/edit')
                ->where('grupo.empleados.0.persona.periodo_vigente', null)
                ->has('personas')
            );
    });

    test('se le cambia la categoria a un integrante sin sacarlo del grupo', function () {
        $grupo = GrupoTrabajo::factory()->create();
        $empleado = GrupoEmpleado::factory()->create([
            'grupo_trabajo_id' => $grupo->id,
            'categoria_empleado_id' => CategoriaEmpleado::factory()->create()->id,
        ]);
        $nueva = CategoriaEmpleado::factory()->create();

        $this->actingAs($this->user)
            ->patch(route('admin.prod.grupos-trabajo.empleados.update', [$grupo, $empleado]), [
                'categoria_empleado_id' => $nueva->id,
            ])
            ->assertRedirect();

        expect($empleado->fresh()->categoria_empleado_id)->toBe($nueva->id);
    });

    test('se le puede quitar la categoria', function () {
        $grupo = GrupoTrabajo::factory()->create();
        $empleado = GrupoEmpleado::factory()->create([
            'grupo_trabajo_id' => $grupo->id,
            'categoria_empleado_id' => CategoriaEmpleado::factory()->create()->id,
        ]);

        $this->actingAs($this->user)
            ->patch(route('admin.prod.grupos-trabajo.empleados.update', [$grupo, $empleado]), [
                'categoria_empleado_id' => null,
            ])
            ->assertSessionHasNoErrors();

        expect($empleado->fresh()->categoria_empleado_id)->toBeNull();
    });

    test('vincular la persona refresca el nombre y el numero copiados de RH', function () {
        $grupo = GrupoTrabajo::factory()->create();
        $categoria = CategoriaEmpleado::factory()->create();
        $empleado = GrupoEmpleado::factory()->create([
            'grupo_trabajo_id' => $grupo->id,
            'persona_id' => null,
            'nombre' => 'Capturado A Mano',
            'no_empleado' => null,
            'categoria_empleado_id' => $categoria->id,
        ]);

        $persona = Persona::factory()->create(['nombre' => 'Jose', 'apellido' => 'Ramirez']);
        PeriodoLaboral::factory()->create([
            'persona_id' => $persona->id,
            'estado' => 'activo',
            'numero_empleado' => 'E123',
        ]);

        $this->actingAs($this->user)
            ->patch(route('admin.prod.grupos-trabajo.empleados.update', [$grupo, $empleado]), [
                'persona_id' => $persona->id,
            ])
            ->assertSessionHasNoErrors();

        $empleado->refresh();

        // La categoria no viajo en la peticion: se queda como estaba.
        expect($empleado->persona_id)->toBe($persona->id)
            ->and($empleado->nombre)->toBe('Jose Ramirez')
            ->and($empleado->no_empleado)->toBe('E123')
            ->and($empleado->categoria_empleado_id)->toBe($categoria->id);
    });

    test('no se vincula a alguien que ya esta en otro grupo', function () {
        $persona = Persona::factory()->create();
        GrupoEmpleado::factory()->create([
            'grupo_trabajo_id' => GrupoTrabajo::factory()->create()->id,
            'persona_id' => $persona->id,
        ]);

        $grupo = GrupoTrabajo::factory()->create();
        $empleado = GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $grupo->id, 'persona_id' => null]);

        $this->actingAs($this->user)
            ->patch(route('admin.prod.grupos-trabajo.empleados.update', [$grupo, $empleado]), [
                'persona_id' => $persona->id,
            ])
            ->assertSessionHasErrors('persona_id');

        expect($empleado->fresh()->persona_id)->toBeNull();
    });

    test('editar a un integrante de otro grupo da 404', function () {
        $empleado = GrupoEmpleado::factory()->create([
            'grupo_trabajo_id' => GrupoTrabajo::factory()->create()->id,
        ]);

        $this->actingAs($this->user)
            ->patch(route('admin.prod.grupos-trabajo.empleados.update', [GrupoTrabajo::factory()->create(), $empleado]), [
                'categoria_empleado_id' => CategoriaEmpleado::factory()->create()->id,
            ])
            ->assertNotFound();
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
