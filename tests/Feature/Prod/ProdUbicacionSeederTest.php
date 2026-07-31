<?php

use App\Models\Prod\Ubicacion;
use Database\Seeders\ProdUbicacionSeeder;

test('siembra el catálogo de módulos de trabajo', function () {
    $this->seed(ProdUbicacionSeeder::class);

    expect(Ubicacion::count())->toBe(107);
    expect(Ubicacion::where('nombre', '1era-Pintura')->exists())->toBeTrue();
    expect(Ubicacion::where('nombre', 'M3.10 Corimp (3PL)')->exists())->toBeTrue();
    expect(Ubicacion::where('nombre', 'SC EN MOD - PINTURA (PLS)')->value('activo'))->toBeTrue();
});

test('es idempotente y respeta las bajas manuales', function () {
    $this->seed(ProdUbicacionSeeder::class);

    Ubicacion::where('nombre', 'M1.1 Fabricacion')->update(['activo' => false]);

    $this->seed(ProdUbicacionSeeder::class);

    expect(Ubicacion::count())->toBe(107);
    expect(Ubicacion::where('nombre', 'M1.1 Fabricacion')->value('activo'))->toBeFalse();
});
