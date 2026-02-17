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
});
