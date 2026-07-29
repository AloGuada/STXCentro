<?php

use App\Models\Concepto;
use App\Models\Prod\Asistencia;
use App\Models\Prod\Catalogo;
use App\Models\Prod\CategoriaEmpleado;
use App\Models\Prod\ConfiguracionProd;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Registro;
use App\Models\Prod\Ubicacion;
use App\Services\Prod\PendientesDeLiquidar;
use Database\Seeders\ProdDevSeeder;

/**
 * Los datos de demo se corren a mano y nadie los prueba: este smoke test evita
 * que el seeder se rompa en silencio cuando cambian las reglas del módulo, y
 * comprueba que los casos interesantes queden efectivamente sembrados.
 */
test('el seeder de datos de ejemplo corre completo', function () {
    $this->seed(ProdDevSeeder::class);

    expect(Destajo::count())->toBe(3)
        ->and(Destajo::where('cerrado', true)->count())->toBe(2)
        ->and(Destajo::where('cerrado', false)->count())->toBe(1)
        ->and(GrupoTrabajo::count())->toBe(4)
        ->and(Ubicacion::count())->toBeGreaterThan(1)
        ->and(CategoriaEmpleado::count())->toBe(4)
        ->and((float) ConfiguracionProd::actual()->salario_minimo_diario)->toBe(300.0);

    $cerrado = Destajo::where('cerrado', true)->firstOrFail();

    expect($cerrado->liquidaciones()->count())->toBeGreaterThan(0)
        ->and(Asistencia::where('destajo_id', $cerrado->id)->count())->toBeGreaterThan(0);
});

test('el seeder deja sembrados los casos de borde del modulo', function () {
    $this->seed(ProdDevSeeder::class);

    // Una obra estrena v2 del catalogo, para el comparador de versiones.
    expect(Catalogo::where('version', 2)->exists())->toBeTrue()
        ->and(Catalogo::where('vigente', true)->count())->toBe(3)
        // Piezas sin grupo de precio: disparan la advertencia antes de cerrar.
        ->and(Concepto::whereDoesntHave('grupoPrecioConceptos')->exists())->toBeTrue()
        // Un lote pagado a medias.
        ->and(Registro::where('porcentaje', '<', 100)->exists())->toBeTrue();

    // Ese parcial se ve como pendiente en la semana abierta.
    $abierto = Destajo::where('cerrado', false)->firstOrFail();

    expect(app(PendientesDeLiquidar::class)->paraDestajo($abierto))->not->toBeEmpty();
});

test('el seeder es idempotente: se puede correr dos veces', function () {
    $this->seed(ProdDevSeeder::class);
    $this->seed(ProdDevSeeder::class);

    expect(Destajo::count())->toBe(3)
        ->and(GrupoTrabajo::count())->toBe(4);
});
