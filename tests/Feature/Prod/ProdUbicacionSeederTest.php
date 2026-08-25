<?php

use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Ubicacion;
use Database\Seeders\ProdUbicacionSeeder;

test('siembra el catálogo de ubicaciones', function () {
    $this->seed(ProdUbicacionSeeder::class);

    expect(Ubicacion::count())->toBe(74);
    expect(Ubicacion::where('nombre', '1T 1era Transformación')->exists())->toBeTrue();
    expect(Ubicacion::where('nombre', '2T Modulo 4.2 Robot Somey')->exists())->toBeTrue();
    expect(Ubicacion::where('nombre', '3T L5 Modulo 16')->exists())->toBeTrue();
    expect(Ubicacion::where('nombre', 'LINCOLN')->value('activo'))->toBeTrue();
});

test('es idempotente y respeta las bajas manuales', function () {
    $this->seed(ProdUbicacionSeeder::class);

    Ubicacion::where('nombre', 'Corimpex')->update(['activo' => false]);

    $this->seed(ProdUbicacionSeeder::class);

    expect(Ubicacion::count())->toBe(74);
    expect(Ubicacion::where('nombre', 'Corimpex')->value('activo'))->toBeFalse();
});

test('borra las ubicaciones viejas incluso si un grupo las tenía asignadas', function () {
    $vieja = Ubicacion::factory()->create(['nombre' => 'M1.1 Fabricacion']);
    $grupo = GrupoTrabajo::factory()->create();
    $grupo->ubicaciones()->attach($vieja);

    $this->seed(ProdUbicacionSeeder::class);

    expect(Ubicacion::where('nombre', 'M1.1 Fabricacion')->exists())->toBeFalse();
    expect($grupo->fresh()->ubicaciones)->toHaveCount(0);
    expect(Ubicacion::count())->toBe(74);
});
