<?php

use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Prod\CategoriaEmpleado;
use App\Models\Prod\ConfiguracionProd;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoEmpleado;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Ubicacion;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('catalogo de ubicaciones', function () {
    test('index lista con su conteo de grupos', function () {
        $ubicacion = Ubicacion::factory()->create();
        GrupoTrabajo::factory()->create()->ubicaciones()->attach($ubicacion);

        $this->actingAs($this->user)
            ->get(route('admin.prod.ubicaciones.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/prod/ubicaciones/index')
                ->where('ubicaciones.data.0.grupos_trabajo_count', 1)
            );
    });

    test('se crea, edita y no admite nombres repetidos', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.ubicaciones.store'), ['nombre' => 'Nave norte', 'activo' => true])
            ->assertRedirect(route('admin.prod.ubicaciones.index'));

        $ubicacion = Ubicacion::where('nombre', 'Nave norte')->firstOrFail();

        $this->actingAs($this->user)
            ->put(route('admin.prod.ubicaciones.update', $ubicacion), ['nombre' => 'Nave sur', 'activo' => false])
            ->assertSessionHasNoErrors();

        expect($ubicacion->fresh()->nombre)->toBe('Nave sur')
            ->and($ubicacion->fresh()->activo)->toBeFalse();

        $this->actingAs($this->user)
            ->post(route('admin.prod.ubicaciones.store'), ['nombre' => 'Nave sur'])
            ->assertSessionHasErrors('nombre');
    });

    test('no se elimina si esta asignada a un grupo', function () {
        $ubicacion = Ubicacion::factory()->create();
        GrupoTrabajo::factory()->create()->ubicaciones()->attach($ubicacion);

        $this->actingAs($this->user)
            ->delete(route('admin.prod.ubicaciones.destroy', $ubicacion))
            ->assertSessionHasErrors('error');

        $this->assertDatabaseHas('prod_ubicaciones', ['id' => $ubicacion->id]);
    });
});

describe('catalogo de categorias de empleado', function () {
    test('se crea con su valor de peso', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.categorias-empleado.store'), [
                'nombre' => 'Oficial',
                'valor' => 2000,
                'orden' => 1,
                'activo' => true,
            ])
            ->assertRedirect(route('admin.prod.categorias-empleado.index'));

        expect(CategoriaEmpleado::where('nombre', 'Oficial')->firstOrFail()->valor)->toBe(2000);
    });

    test('el valor es obligatorio y mayor a cero', function () {
        $this->actingAs($this->user)
            ->post(route('admin.prod.categorias-empleado.store'), ['nombre' => 'Sin valor'])
            ->assertSessionHasErrors('valor');

        $this->actingAs($this->user)
            ->post(route('admin.prod.categorias-empleado.store'), ['nombre' => 'Cero', 'valor' => 0])
            ->assertSessionHasErrors('valor');
    });

    test('no se elimina si tiene empleados', function () {
        $categoria = CategoriaEmpleado::factory()->create();
        GrupoEmpleado::factory()->create(['categoria_empleado_id' => $categoria->id]);

        $this->actingAs($this->user)
            ->delete(route('admin.prod.categorias-empleado.destroy', $categoria))
            ->assertSessionHasErrors('error');
    });
});

describe('configuracion del modulo', function () {
    test('se guarda el salario minimo diario', function () {
        $this->actingAs($this->user)
            ->put(route('admin.prod.configuracion.update'), ['salario_minimo_diario' => 312.50])
            ->assertSessionHasNoErrors();

        expect((float) ConfiguracionProd::actual()->salario_minimo_diario)->toBe(312.5);
    });

    test('rechaza valores negativos', function () {
        $this->actingAs($this->user)
            ->put(route('admin.prod.configuracion.update'), ['salario_minimo_diario' => -1])
            ->assertSessionHasErrors('salario_minimo_diario');
    });

    test('la pantalla abre aunque nunca se haya configurado', function () {
        $this->actingAs($this->user)
            ->get(route('admin.prod.configuracion.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/prod/configuracion/edit')->has('configuracion'));
    });
});

test('capturar produccion NO exige asistencia; el candado es solo al cerrar', function () {
    $catalogo = Catalogo::factory()->create();
    $pieza = Concepto::factory()->create([
        'obra_id' => $catalogo->obra_id,
        'catalogo_id' => $catalogo->id,
        'cantidad' => 100,
    ]);
    $grupo = GrupoTrabajo::factory()->create();
    GrupoEmpleado::factory()->create(['grupo_trabajo_id' => $grupo->id]);

    $destajo = Destajo::factory()->create([
        'fecha_inicio' => '2026-02-02',
        'fecha_fin' => '2026-02-08',
    ]);

    // Sin una sola marca de asistencia, la captura pasa.
    $this->actingAs($this->user)
        ->post(route('admin.prod.destajos.registros.store', $destajo), [
            'fecha' => '2026-02-04',
            'concepto_id' => $pieza->id,
            'grupo_trabajo_id' => $grupo->id,
            'cantidad' => 5,
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('prod_registros', ['concepto_id' => $pieza->id, 'cantidad' => 5]);

    // Y el cierre es lo unico que se bloquea.
    $this->actingAs($this->user)
        ->post(route('admin.prod.destajos.cerrar', $destajo))
        ->assertSessionHasErrors('error');
});
