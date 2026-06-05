<?php

use App\Models\Costos\Rubro;
use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin costos presupuestos', function () {
    test('index page can be rendered with obras and sums', function () {
        $rubro = Rubro::factory()->create();
        $obra = Obra::factory()->create();

        // Auto-created ObraRubro should exist, update it with test values
        $obra->obraRubros()->where('rubro_id', $rubro->id)->update([
            'presupuestado' => 50000,
            'acumulado' => 10000,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.presupuestos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/presupuestos/index')
            ->has('obras.data', 1)
        );
    });

    test('index page supports search', function () {
        Obra::factory()->create(['no' => 'OBR-SEARCH-001', 'descripcion' => 'Obra Buscada']);
        Obra::factory()->create(['no' => 'OBR-OTHER-002', 'descripcion' => 'Otra Obra']);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.presupuestos.index', ['search' => 'SEARCH']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('obras.data', 1)
        );
    });

    test('edit page can be rendered with obra rubros', function () {
        $rubro = Rubro::factory()->create();
        $obra = Obra::factory()->create();

        // Obra should auto-have the rubro assigned
        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.presupuestos.edit', $obra));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/presupuestos/edit')
            ->has('obra.obra_rubros', 1)
            ->has('rubros')
        );
    });

    test('creating obra auto-assigns all existing rubros', function () {
        Rubro::factory()->count(3)->create();

        $obra = Obra::factory()->create();

        expect($obra->obraRubros)->toHaveCount(3);
        expect($obra->obraRubros->every(fn ($or) => $or->presupuestado == 0 && $or->acumulado == 0))->toBeTrue();
    });

    test('creating rubro auto-assigns it to all existing obras', function () {
        Obra::factory()->count(2)->create();

        $rubro = Rubro::factory()->create();

        $this->assertDatabaseCount('costos_obra_rubros', 2);
        $this->assertDatabaseHas('costos_obra_rubros', [
            'rubro_id' => $rubro->id,
            'presupuestado' => 0,
        ]);
    });

    test('creating planta auto-assigns only planta rubros', function () {
        Rubro::factory()->count(2)->create(['ambito' => 'obra']);
        Rubro::factory()->planta()->create();

        $planta = Obra::factory()->planta()->create();

        expect($planta->obraRubros)->toHaveCount(1);
    });

    test('proyecto de planta can be created from presupuestos', function () {
        Rubro::factory()->planta()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.presupuestos.planta.store'), [
                'descripcion' => 'Gasto Operativo Planta',
            ]);

        $planta = Obra::where('es_planta', true)->first();

        expect($planta)->not->toBeNull();
        $response->assertRedirect(route('admin.costos.presupuestos.edit', $planta));
        expect($planta->no)->toBe('PLANTA');
        expect($planta->obraRubros)->toHaveCount(1);
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
        Obra::factory()->create();
        Obra::factory()->planta()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.presupuestos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('obras.data', 1)
            ->where('planta.es_planta', true)
            ->has('statsPlanta')
        );
    });

    test('index sin planta envia planta null', function () {
        Obra::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.presupuestos.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('planta', null)
            ->where('statsPlanta', null)
        );
    });

    test('stats de obras no incluyen el presupuesto de la planta', function () {
        $rubroObra = Rubro::factory()->create(['ambito' => 'obra']);
        $rubroPlanta = Rubro::factory()->planta()->create();

        $obra = Obra::factory()->create();
        $planta = Obra::factory()->planta()->create();

        $obra->obraRubros()->where('rubro_id', $rubroObra->id)->update(['presupuestado' => 1000]);
        $planta->obraRubros()->where('rubro_id', $rubroPlanta->id)->update(['presupuestado' => 9000]);

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

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.presupuestos.edit', $planta));

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

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.presupuestos.edit', $obra));

        $response->assertInertia(fn ($page) => $page->has('rubros', 2));
    });
});
