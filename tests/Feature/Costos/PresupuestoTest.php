<?php

use App\Models\Costos\Presupuesto;
use App\Models\Costos\Rubro;
use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    foreach (['ver', 'crear', 'editar'] as $accion) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => "costos.obra-rubros.{$accion}", 'guard_name' => 'web']);
    }
    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['costos.obra-rubros.ver', 'costos.obra-rubros.crear', 'costos.obra-rubros.editar']);
});

/** Crea un presupuesto de obra con un rubro sembrado. */
function presupuestoDeObraConRubro(array $obraAttrs = [], float $presupuestado = 0, float $acumulado = 0): Presupuesto
{
    $obra = Obra::factory()->create($obraAttrs);
    $presupuesto = Presupuesto::factory()->paraObra($obra)->create();
    $ambito = $obra->es_planta ? 'planta' : 'obra';
    $rubro = Rubro::factory()->create(['ambito' => $ambito]);
    $or = $presupuesto->crearRubro($rubro->id, $presupuestado);
    $or->update(['acumulado' => $acumulado]);

    return $presupuesto;
}

describe('admin costos presupuestos', function () {
    test('index page can be rendered with presupuestos and sums', function () {
        presupuestoDeObraConRubro([], 50000, 10000);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.presupuestos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/presupuestos/index')
            ->has('presupuestos.data', 1)
            ->where('presupuestos.data.0.tipo', 'obra')
        );
    });

    test('index page supports search', function () {
        $obra = Obra::factory()->create(['no' => 'OBR-SEARCH-001', 'descripcion' => 'Obra Buscada']);
        Presupuesto::factory()->paraObra($obra)->create();
        $otra = Obra::factory()->create(['no' => 'OBR-OTHER-002', 'descripcion' => 'Otra Obra']);
        Presupuesto::factory()->paraObra($otra)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.presupuestos.index', ['search' => 'SEARCH']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('presupuestos.data', 1)
        );
    });

    test('edit page can be rendered with obra rubros', function () {
        $presupuesto = presupuestoDeObraConRubro();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.presupuestos.edit', $presupuesto));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/presupuestos/edit')
            ->has('presupuesto.obra_rubros', 1)
            ->has('rubros')
        );
    });

    test('crear presupuesto siembra todos los rubros del ambito obra', function () {
        Rubro::factory()->count(3)->create(['ambito' => 'obra']);
        Rubro::factory()->planta()->create();
        $obra = Obra::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.presupuestos.store'), [
                'presupuestable_type' => 'obra',
                'presupuestable_id' => $obra->id,
            ]);

        $presupuesto = Presupuesto::firstWhere('presupuestable_id', $obra->id);
        expect($presupuesto)->not->toBeNull();
        $response->assertRedirect(route('admin.costos.presupuestos.edit', $presupuesto));
        expect($presupuesto->rubros()->count())->toBe(3);
    });

    test('crear presupuesto sobre un proyecto', function () {
        Rubro::factory()->count(2)->create(['ambito' => 'obra']);
        $proyecto = \App\Models\Proyecto::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.costos.presupuestos.store'), [
                'presupuestable_type' => 'proyecto',
                'presupuestable_id' => $proyecto->id,
            ])->assertRedirect();

        $presupuesto = Presupuesto::firstWhere('presupuestable_type', \App\Models\Proyecto::class);
        expect($presupuesto->presupuestable_id)->toBe($proyecto->id);
        expect($presupuesto->rubros()->count())->toBe(2);
    });

    test('proyecto de planta can be created from presupuestos', function () {
        Rubro::factory()->planta()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.presupuestos.planta.store'), [
                'descripcion' => 'Gasto Operativo Planta',
            ]);

        $planta = Obra::where('es_planta', true)->first();

        expect($planta)->not->toBeNull();
        $presupuesto = $planta->presupuesto;
        $response->assertRedirect(route('admin.costos.presupuestos.edit', $presupuesto));
        expect($planta->no)->toBe('PLANTA');
        expect($presupuesto->rubros()->count())->toBe(1);
    });

    test('solo puede existir un proyecto de planta', function () {
        Obra::factory()->planta()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.presupuestos.planta.store'), [
                'descripcion' => 'Otra Planta',
            ]);

        $response->assertSessionHasErrors(['descripcion']);
        expect(Obra::where('es_planta', true)->count())->toBe(1);
    });

    test('index excluye la planta del listado y la envia como prop separada', function () {
        presupuestoDeObraConRubro();
        presupuestoDeObraConRubro(['es_planta' => true]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.presupuestos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('presupuestos.data', 1)
            ->where('planta.es_planta', true)
            ->has('statsPlanta')
        );
    });

    test('index sin planta envia planta null', function () {
        presupuestoDeObraConRubro();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.presupuestos.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('planta', null)
            ->where('statsPlanta', null)
        );
    });

    test('stats de obras no incluyen el presupuesto de la planta', function () {
        presupuestoDeObraConRubro([], 1000);
        presupuestoDeObraConRubro(['es_planta' => true], 9000);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.presupuestos.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('stats.total_presupuestado', 1000)
            ->where('statsPlanta.total_presupuestado', 9000)
        );
    });

    test('edit de la planta solo ofrece rubros de planta', function () {
        Rubro::factory()->count(2)->create(['ambito' => 'obra']);
        $rubroPlanta = Rubro::factory()->planta()->create();

        $planta = Obra::factory()->planta()->create();
        $presupuesto = Presupuesto::factory()->paraObra($planta)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.presupuestos.edit', $presupuesto));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('rubros', 1)
            ->where('rubros.0.id', $rubroPlanta->id)
        );
    });

    test('edit de obra normal solo ofrece rubros de obra', function () {
        Rubro::factory()->count(2)->create(['ambito' => 'obra']);
        Rubro::factory()->planta()->create();

        $obra = Obra::factory()->create();
        $presupuesto = Presupuesto::factory()->paraObra($obra)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.presupuestos.edit', $presupuesto));

        $response->assertInertia(fn ($page) => $page->has('rubros', 2));
    });
});
