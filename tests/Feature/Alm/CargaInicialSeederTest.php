<?php

use App\Enums\Alm\AjusteMotivo;
use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Ajuste;
use App\Models\Alm\AjusteDetalle;
use App\Models\Alm\Almacen;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\Costos\Producto;
use App\Models\User;
use Database\Seeders\Alm\CargaInicialSeeder;
use Database\Seeders\Alm\PinturaSeeder;
use Database\Seeders\Alm\SoldaduraSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->autoriza = User::factory()->create();
    $this->autoriza->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
});

function almacenCentral(string $clave): Almacen
{
    return Almacen::factory()->create(['clave' => $clave, 'obra_id' => null]);
}

test('soldadura abre su almacén con los números que entregó el área', function () {
    almacenCentral('SOL');

    $this->seed(SoldaduraSeeder::class);

    expect(Producto::count())->toBe(11)
        ->and(round((float) Existencia::sum('valor'), 2))->toBe(287204.20);

    $ajuste = Ajuste::sole();

    expect($ajuste->motivo)->toBe(AjusteMotivo::CargaInicial)
        ->and($ajuste->folio)->toStartWith('AJU-')
        ->and($ajuste->autorizado_por)->toBe($this->autoriza->id);

    $articulo = Producto::where('descripcion', 'FUNDENTE PARA SOLDADURA')->sole();

    expect($articulo->codigo)->toStartWith('ART-')
        // La etiqueta que se imprime es la nuestra: nacen sin código de fábrica.
        ->and($articulo->codigo_barras)->toBe($articulo->codigo)
        ->and($articulo->unidad)->toBe('KG')
        // Nace sin área: el catálogo de áreas se capturó como el área de quien
        // recibe, no como la familia del artículo. Se clasifica después.
        ->and($articulo->area_id)->toBeNull()
        ->and($articulo->tipo->value)->toBe('insumo')
        ->and($articulo->controla_inventario)->toBeTrue()
        ->and($articulo->se_controla_por_pieza)->toBeFalse()
        ->and($articulo->clasificacion_abc->value)->toBe('A');

    // El saldo entró por el ledger, no a mano.
    $movimiento = Movimiento::where('producto_id', $articulo->id)->sole();

    expect($movimiento->tipo)->toBe(MovimientoTipo::Ajuste)
        ->and((float) $movimiento->saldo_antes)->toBe(0.0)
        ->and((float) $movimiento->saldo_despues)->toBe(900.0)
        ->and($movimiento->referencia)->toBe($ajuste->folio);

    // Contar cero es información: el renglón queda, pero no es un movimiento.
    $enCero = Producto::where('descripcion', 'GRANALLA DE ACERO')->sole();

    expect(AjusteDetalle::where('producto_id', $enCero->id)->exists())->toBeTrue()
        ->and(Movimiento::where('producto_id', $enCero->id)->exists())->toBeFalse();
});

test('pintura entra con el costo por litro, no por tambor', function () {
    almacenCentral('PIN');

    $this->seed(PinturaSeeder::class);

    expect(Producto::count())->toBe(39)
        ->and(round((float) Existencia::sum('valor'), 2))->toBe(1034070.75);

    $primario = Producto::where('descripcion', 'like', 'Primario anticorrosivo%')->sole();
    $existencia = Existencia::where('producto_id', $primario->id)->sole();

    // $11,400 era el tambor de 200 litros; el litro sale en $57.
    expect((float) $existencia->cantidad)->toBe(1650.0)
        ->and((float) $existencia->costo_promedio)->toBe(57.0)
        ->and((float) $existencia->valor)->toBe(94050.0);

    // Con la cuenta a la vista en el renglón, y la obra a la que está asignado.
    expect(AjusteDetalle::where('producto_id', $primario->id)->value('observaciones'))
        ->toContain('Costo prorrateado del envase de 200 LTS')
        ->toContain('Asignado a: Planta');
});

/**
 * Un ajuste de inventario es un hecho fechado, no un estado al que se pueda
 * converger: la segunda corrida levantaría artículos con código nuevo y un
 * ajuste con folio nuevo, duplicando catálogo y saldo.
 */
test('no se vuelve a cargar un almacén que ya abrió', function () {
    almacenCentral('SOL');

    $this->seed(SoldaduraSeeder::class);
    $this->seed(SoldaduraSeeder::class);

    expect(Producto::count())->toBe(11)
        ->and(Ajuste::count())->toBe(1);
});

test('no carga nada si el almacén no está dado de alta', function () {
    $this->seed(SoldaduraSeeder::class);

    expect(Producto::count())->toBe(0)
        ->and(Ajuste::count())->toBe(0);
});

/**
 * Los dos almacenes que abrieron entran sin área, pero la salvaguarda sigue
 * viva para el que llegue clasificado: si nombra un área que no está en el
 * catálogo se cae entera, porque media carga inicial es peor que ninguna.
 */
test('se revierte completa cuando falta el área en el catálogo', function () {
    $almacen = almacenCentral('SOL');

    $seeder = new class extends CargaInicialSeeder
    {
        protected function almacen(): string
        {
            return 'SOL';
        }

        protected function articulos(): array
        {
            return [
                ['descripcion' => 'TORNILLO A325 3/4', 'unidad' => 'PZA', 'area' => 'Tornillería', 'abc' => 'B', 'stock_minimo' => 100, 'cantidad' => 500, 'costo' => 12.5, 'nota' => null],
            ];
        }
    };

    expect(fn () => $seeder->run())->toThrow(RuntimeException::class);

    expect(Producto::count())->toBe(0)
        ->and(Ajuste::where('almacen_id', $almacen->id)->count())->toBe(0)
        ->and(Existencia::count())->toBe(0);
});
