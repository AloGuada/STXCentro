<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\Rubro;
use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin costos obra rubros', function () {
    test('obra rubro can be stored', function () {
        $rubro = Rubro::factory()->create(['ambito' => 'obra']);
        $presupuesto = Presupuesto::factory()->paraObra()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.obra-rubros.store'), [
                'presupuesto_id' => $presupuesto->id,
                'rubro_id' => $rubro->id,
                'presupuestado' => 50000.00,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('costos_obra_rubros', [
            'presupuesto_id' => $presupuesto->id,
            'rubro_id' => $rubro->id,
            'presupuestado' => 50000.00,
        ]);
    });

    test('obra rubro can be updated', function () {
        $presupuesto = Presupuesto::factory()->paraObra()->create();
        $obraRubro = ObraRubro::factory()->create(['presupuesto_id' => $presupuesto->id]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.costos.obra-rubros.update', $obraRubro), [
                'presupuestado' => 75000.00,
                'acumulado' => 12000.00,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('costos_obra_rubros', [
            'id' => $obraRubro->id,
            'presupuestado' => 75000.00,
            'acumulado' => 12000.00,
        ]);
    });

    test('obra rubro can be deleted', function () {
        $obraRubro = ObraRubro::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.costos.obra-rubros.destroy', $obraRubro));

        $response->assertRedirect();
        $this->assertDatabaseMissing('costos_obra_rubros', ['id' => $obraRubro->id]);
    });

    test('store rechaza rubro de planta en presupuesto de obra', function () {
        $presupuesto = Presupuesto::factory()->paraObra()->create();
        $rubroPlanta = Rubro::factory()->planta()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.obra-rubros.store'), [
                'presupuesto_id' => $presupuesto->id,
                'rubro_id' => $rubroPlanta->id,
                'presupuestado' => 1000,
            ]);

        $response->assertSessionHasErrors(['rubro_id']);
        $this->assertDatabaseMissing('costos_obra_rubros', [
            'presupuesto_id' => $presupuesto->id,
            'rubro_id' => $rubroPlanta->id,
        ]);
    });

    test('store rechaza rubro de obra en presupuesto de planta', function () {
        $planta = Obra::factory()->planta()->create();
        $presupuesto = Presupuesto::factory()->paraObra($planta)->create();
        $rubroObra = Rubro::factory()->create(['ambito' => 'obra']);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.obra-rubros.store'), [
                'presupuesto_id' => $presupuesto->id,
                'rubro_id' => $rubroObra->id,
                'presupuestado' => 1000,
            ]);

        $response->assertSessionHasErrors(['rubro_id']);
    });

    test('store acepta rubro de planta en presupuesto de planta', function () {
        $planta = Obra::factory()->planta()->create();
        $presupuesto = Presupuesto::factory()->paraObra($planta)->create();
        $rubroPlanta = Rubro::factory()->planta()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.obra-rubros.store'), [
                'presupuesto_id' => $presupuesto->id,
                'rubro_id' => $rubroPlanta->id,
                'presupuestado' => 25000,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('costos_obra_rubros', [
            'presupuesto_id' => $presupuesto->id,
            'rubro_id' => $rubroPlanta->id,
            'presupuestado' => 25000.00,
        ]);
    });

    test('store all asigna los rubros faltantes del ambito con presupuesto cero', function () {
        $presupuesto = Presupuesto::factory()->paraObra()->create();
        Rubro::factory()->count(3)->create(['ambito' => 'obra']);
        $rubroPlanta = Rubro::factory()->planta()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.obra-rubros.store-all'), [
                'presupuesto_id' => $presupuesto->id,
            ]);

        $response->assertRedirect();

        $rubros = $presupuesto->rubros()->get();
        expect($rubros)->toHaveCount(3);
        expect($rubros->pluck('presupuestado')->unique()->all())->toBe(['0.00']);
        $this->assertDatabaseMissing('costos_obra_rubros', [
            'presupuesto_id' => $presupuesto->id,
            'rubro_id' => $rubroPlanta->id,
        ]);
    });

    test('store all no duplica los rubros ya asignados', function () {
        $presupuesto = Presupuesto::factory()->paraObra()->create();
        $existente = Rubro::factory()->create(['ambito' => 'obra']);
        Rubro::factory()->count(2)->create(['ambito' => 'obra']);

        $presupuesto->crearRubro($existente->id, 5000);

        $this->actingAs($this->user)
            ->post(route('admin.costos.obra-rubros.store-all'), ['presupuesto_id' => $presupuesto->id])
            ->assertRedirect();

        expect($presupuesto->rubros()->count())->toBe(3);
        $this->assertDatabaseHas('costos_obra_rubros', [
            'presupuesto_id' => $presupuesto->id,
            'rubro_id' => $existente->id,
            'presupuestado' => 5000.00,
        ]);
    });

    test('validation requires presupuesto_id, rubro_id and presupuestado', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.obra-rubros.store'), []);

        $response->assertSessionHasErrors(['presupuesto_id', 'rubro_id', 'presupuestado']);
    });

    test('obra edit muestra los obra rubros existentes', function () {
        $obra = Obra::factory()->create();
        $presupuesto = Presupuesto::factory()->paraObra($obra)->create();
        $rubro = Rubro::factory()->create(['ambito' => 'obra']);
        $presupuesto->crearRubro($rubro->id, 1000);

        $response = $this->actingAs($this->user)
            ->get(route('admin.obras.edit', $obra));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('obra.obra_rubros', 1)
            ->has('rubros')
        );
    });
});
