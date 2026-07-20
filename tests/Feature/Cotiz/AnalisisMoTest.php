<?php

use App\Models\Cotiz\FaseMontaje;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraFleteEstandar;
use App\Models\Cotiz\ObraFleteViatico;
use App\Models\Cotiz\PersonalCategoria;
use App\Models\Cotiz\SeccionFaseRendimiento;
use App\Models\Cotiz\SeccionMontaje;
use App\Models\Cotiz\Tarjeta;
use App\Models\User;
use Database\Seeders\RolesSeeder;

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->seed(RolesSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-cotiz');
    $this->actingAs($this->user);
});

describe('analisis-mo — index', function () {
    test('renderiza la página con las 4 secciones de datos', function () {
        $obra = Obra::factory()->create();

        $this->get(route('admin.cotiz.analisis-mo.index', $obra))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cotiz/analisis-mo/index')
                ->has('obra')
                ->has('secciones')
                ->has('cuadrilla.categorias')
                ->has('cuadrilla.totales')
                ->has('fletesViaticos.items')
                ->has('fletesViaticos.variables')
                ->has('fletesEstandar')
                ->has('catalogos.metodos')
            );
    });
});

describe('secciones de montaje', function () {
    test('alta crea zona y redirige a su edición', function () {
        $obra = Obra::factory()->create();

        $this->post(route('admin.cotiz.secciones.store', $obra), ['nombre' => 'Nave A'])
            ->assertRedirect();

        $this->assertDatabaseHas('cotiz_secciones_montaje', ['obra_id' => $obra->id, 'nombre' => 'Nave A']);
    });

    test('editar renderiza la matriz personal y rendimientos', function () {
        $seccion = SeccionMontaje::factory()->create();

        $this->get(route('admin.cotiz.secciones.edit', $seccion))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cotiz/analisis-mo/seccion-edit')
                ->has('seccion')
                ->has('categorias')
                ->has('fases')
                ->has('rendimientos')
                ->has('resumenPorFase')
            );
    });

    test('upsert de celda de personal es idempotente por (sección, fase, categoría)', function () {
        $seccion = SeccionMontaje::factory()->create();
        $fase = FaseMontaje::factory()->create();
        $cat = PersonalCategoria::factory()->create();

        $payload = ['fase_id' => $fase->id, 'categoria_id' => $cat->id, 'cantidad' => 4];
        $this->put(route('admin.cotiz.secciones.personal.upsert', $seccion), $payload)->assertRedirect();
        $this->put(route('admin.cotiz.secciones.personal.upsert', $seccion), [...$payload, 'cantidad' => 7])->assertRedirect();

        expect(\App\Models\Cotiz\SeccionPersonal::where('seccion_id', $seccion->id)->count())->toBe(1);
        $this->assertDatabaseHas('cotiz_seccion_personal', ['seccion_id' => $seccion->id, 'fase_id' => $fase->id, 'cantidad' => 7]);
    });

    test('alta y borrado de renglón de rendimiento', function () {
        $seccion = SeccionMontaje::factory()->create();
        $fase = FaseMontaje::factory()->create();

        $this->post(route('admin.cotiz.secciones.rendimientos.store', $seccion), ['fase_id' => $fase->id])->assertRedirect();
        $rend = SeccionFaseRendimiento::where('seccion_id', $seccion->id)->firstOrFail();

        $this->put(route('admin.cotiz.secciones.rendimientos.update', $rend), ['cantidad' => 12, 'rendimiento' => 4])->assertRedirect();
        $this->assertDatabaseHas('cotiz_seccion_fase_rendimiento', ['id' => $rend->id, 'cantidad' => 12]);

        $this->delete(route('admin.cotiz.secciones.rendimientos.destroy', $rend))->assertRedirect();
        $this->assertDatabaseMissing('cotiz_seccion_fase_rendimiento', ['id' => $rend->id]);
    });
});

describe('cuadrilla global', function () {
    test('upsert de celda y actualización de num_grupos', function () {
        $obra = Obra::factory()->create(['num_grupos' => 1]);
        $cat = PersonalCategoria::factory()->create();

        $this->put(route('admin.cotiz.cuadrilla-global.upsert', $obra), ['categoria_id' => $cat->id, 'cantidad_por_grupo' => 5])->assertRedirect();
        $this->assertDatabaseHas('cotiz_obra_cuadrilla_global', ['obra_id' => $obra->id, 'categoria_id' => $cat->id, 'cantidad_por_grupo' => 5]);

        $this->put(route('admin.cotiz.cuadrilla-global.num-grupos', $obra), ['num_grupos' => 4])->assertRedirect();
        $this->assertDatabaseHas('cotiz_obras', ['id' => $obra->id, 'num_grupos' => 4]);
    });
});

describe('fletes y viáticos por obra', function () {
    test('alta, importar plantilla y update con recálculo de fórmula', function () {
        $obra = Obra::factory()->create(['num_grupos' => 3]);

        $this->post(route('admin.cotiz.obras.fletes-viaticos.store', $obra), ['grupo' => 'VIATICOS'])->assertRedirect();
        $item = ObraFleteViatico::where('obra_id', $obra->id)->firstOrFail();

        // Una fórmula de cantidad se evalúa y persiste tras el update (grupos = num_grupos = 3).
        $this->put(route('admin.cotiz.obras.fletes-viaticos.update', $item), ['formula_cantidad' => 'grupos'])->assertRedirect();
        expect((float) $item->fresh()->cantidad)->toEqualWithDelta(3.0, 1e-9);

        \App\Models\Cotiz\FleteViaticoCatalogo::factory()->count(3)->create();
        $this->post(route('admin.cotiz.obras.fletes-viaticos.importar', $obra))->assertRedirect();
        expect(ObraFleteViatico::where('obra_id', $obra->id)->count())->toBe(4); // 1 + 3 importados
    });

    test('fórmula inválida es rechazada', function () {
        $obra = Obra::factory()->create();
        $item = ObraFleteViatico::factory()->create(['obra_id' => $obra->id]);

        $this->put(route('admin.cotiz.obras.fletes-viaticos.update', $item), ['formula_cantidad' => '2 +'])
            ->assertSessionHasErrors('formula_cantidad');
    });
});

describe('fletes estándar por obra', function () {
    test('alta de tarjeta, update y borrado', function () {
        $obra = Obra::factory()->create();
        $tarjeta = Tarjeta::factory()->create(['obra_id' => $obra->id]);

        $this->post(route('admin.cotiz.obras.fletes-estandar.store', $obra), ['tarjeta_id' => $tarjeta->id])->assertRedirect();
        $flete = ObraFleteEstandar::where('obra_id', $obra->id)->firstOrFail();

        $this->put(route('admin.cotiz.obras.fletes-estandar.update', $flete), ['metodo' => 'por_piezas'])->assertRedirect();
        $this->assertDatabaseHas('cotiz_obra_flete_estandar', ['id' => $flete->id, 'metodo' => 'por_piezas']);

        $this->delete(route('admin.cotiz.obras.fletes-estandar.destroy', $flete))->assertRedirect();
        $this->assertDatabaseMissing('cotiz_obra_flete_estandar', ['id' => $flete->id]);
    });
});
