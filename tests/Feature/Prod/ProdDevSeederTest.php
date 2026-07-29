<?php

use App\Models\Prod\Asistencia;
use App\Models\Prod\Destajo;
use Database\Seeders\ProdDevSeeder;

/**
 * Los datos de demo se corren a mano y nadie los prueba: este smoke test evita
 * que el seeder se rompa en silencio cuando cambian las reglas del módulo.
 */
test('el seeder de datos de ejemplo corre completo', function () {
    $this->seed(ProdDevSeeder::class);

    $cerrado = Destajo::where('cerrado', true)->firstOrFail();

    expect(Destajo::count())->toBe(2)
        ->and($cerrado->liquidaciones()->count())->toBeGreaterThan(0)
        // El destajo cerrado trae asistencia, que es requisito para cerrarlo.
        ->and(Asistencia::where('destajo_id', $cerrado->id)->count())->toBeGreaterThan(0);
});
