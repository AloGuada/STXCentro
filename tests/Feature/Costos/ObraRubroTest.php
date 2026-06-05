<?php

use App\Models\Costos\Rubro;
use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin costos obra rubros', function () {
    test('obra rubro can be stored after deletion', function () {
        $rubro = Rubro::factory()->create();
        $obra = Obra::factory()->create();

        // Auto-created, delete it to test store
        $obra->obraRubros()->where('rubro_id', $rubro->id)->delete();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.obra-rubros.store'), [
                'obra_id' => $obra->id,
                'rubro_id' => $rubro->id,
                'presupuestado' => 50000.00,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('costos_obra_rubros', [
            'obra_id' => $obra->id,
            'rubro_id' => $rubro->id,
            'presupuestado' => 50000.00,
        ]);
    });

    test('obra rubro can be updated', function () {
        $rubro = Rubro::factory()->create();
        $obra = Obra::factory()->create();

        $obraRubro = $obra->obraRubros()->where('rubro_id', $rubro->id)->first();

        $response = $this->actingAs($this->user)
            ->put(route('admin.costos.obra-rubros.update', $obraRubro), [
                'presupuestado' => 75000.00,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('costos_obra_rubros', [
            'id' => $obraRubro->id,
            'presupuestado' => 75000.00,
        ]);
    });

    test('obra rubro can be deleted', function () {
        $rubro = Rubro::factory()->create();
        $obra = Obra::factory()->create();

        $obraRubro = $obra->obraRubros()->where('rubro_id', $rubro->id)->first();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.costos.obra-rubros.destroy', $obraRubro));

        $response->assertRedirect();
        $this->assertDatabaseMissing('costos_obra_rubros', ['id' => $obraRubro->id]);
    });

    test('store rechaza rubro de planta en obra normal', function () {
        $obra = Obra::factory()->create();
        $rubroPlanta = Rubro::factory()->planta()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.obra-rubros.store'), [
                'obra_id' => $obra->id,
                'rubro_id' => $rubroPlanta->id,
                'presupuestado' => 1000,
            ]);

        $response->assertSessionHasErrors(['rubro_id']);
        $this->assertDatabaseMissing('costos_obra_rubros', [
            'obra_id' => $obra->id,
            'rubro_id' => $rubroPlanta->id,
        ]);
    });

    test('store rechaza rubro de obra en la planta', function () {
        $planta = Obra::factory()->planta()->create();
        $rubroObra = Rubro::factory()->create(['ambito' => 'obra']);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.obra-rubros.store'), [
                'obra_id' => $planta->id,
                'rubro_id' => $rubroObra->id,
                'presupuestado' => 1000,
            ]);

        $response->assertSessionHasErrors(['rubro_id']);
    });

    test('store acepta rubro de planta en la planta', function () {
        $planta = Obra::factory()->planta()->create();
        $rubroPlanta = Rubro::factory()->planta()->create();

        $planta->obraRubros()->where('rubro_id', $rubroPlanta->id)->delete();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.obra-rubros.store'), [
                'obra_id' => $planta->id,
                'rubro_id' => $rubroPlanta->id,
                'presupuestado' => 25000,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('costos_obra_rubros', [
            'obra_id' => $planta->id,
            'rubro_id' => $rubroPlanta->id,
            'presupuestado' => 25000.00,
        ]);
    });

    test('validation requires obra_id, rubro_id and presupuestado', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.obra-rubros.store'), []);

        $response->assertSessionHasErrors(['obra_id', 'rubro_id', 'presupuestado']);
    });

    test('obra edit shows auto-assigned obra rubros', function () {
        $rubro = Rubro::factory()->create();
        $obra = Obra::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.obras.edit', $obra));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('obra.obra_rubros', 1)
            ->has('rubros')
        );
    });
});
