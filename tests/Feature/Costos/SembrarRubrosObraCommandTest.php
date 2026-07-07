<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\Rubro;
use App\Models\Obra;

beforeEach(function () {
    // Tres centros de costo de tipo obra y uno de planta (que no debe sembrarse).
    $this->rubrosObra = Rubro::factory()->count(3)->create(['ambito' => 'obra']);
    Rubro::factory()->create(['ambito' => 'planta']);
});

it('en dry-run reporta faltantes sin crear nada', function () {
    $presupuesto = Presupuesto::factory()->paraObra()->create();

    $this->artisan('costos:sembrar-rubros-obra')->assertSuccessful();

    expect($presupuesto->rubros()->count())->toBe(0);
});

it('con --force siembra los centros de costo de obra faltantes en cada presupuesto', function () {
    $presupuesto = Presupuesto::factory()->paraObra()->create();
    // Ya tiene uno de los tres: solo deben sembrarse los otros dos.
    ObraRubro::factory()->create([
        'presupuesto_id' => $presupuesto->id,
        'rubro_id' => $this->rubrosObra->first()->id,
    ]);

    $this->artisan('costos:sembrar-rubros-obra', ['--force' => true])->assertSuccessful();

    expect($presupuesto->rubros()->count())->toBe(3)
        ->and($presupuesto->rubros()->pluck('rubro_id')->sort()->values()->all())
        ->toEqual($this->rubrosObra->pluck('id')->sort()->values()->all());
});

it('no siembra centros de costo de planta en presupuestos de obra', function () {
    $presupuesto = Presupuesto::factory()->paraObra()->create();

    $this->artisan('costos:sembrar-rubros-obra', ['--force' => true])->assertSuccessful();

    // Solo los 3 de obra, ninguno de planta.
    expect($presupuesto->rubros()->count())->toBe(3);
});

it('no toca el presupuesto de planta', function () {
    $planta = Presupuesto::factory()->paraObra(Obra::factory()->create(['es_planta' => true]))->create();

    $this->artisan('costos:sembrar-rubros-obra', ['--force' => true])->assertSuccessful();

    expect($planta->rubros()->count())->toBe(0);
});
