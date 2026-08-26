<?php

use App\Models\Alm\Almacen;
use App\Models\Alm\Area;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\Alm\Ubicacion;
use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Producto;
use App\Models\User;
use Database\Seeders\Alm\SoldaduraSeeder;
use Database\Seeders\AlmResetSeeder;
use Spatie\Permission\Models\Role;

test('deja el módulo en cero, catálogo incluido', function () {
    $autoriza = User::factory()->create();
    $autoriza->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));

    $almacen = Almacen::factory()->create(['clave' => 'SOL', 'obra_id' => null]);
    Ubicacion::factory()->create(['almacen_id' => $almacen->id]);
    Area::create(['descripcion' => 'Tornillería', 'activo' => true]);

    $this->seed(SoldaduraSeeder::class);

    expect(Producto::count())->toBe(11)
        ->and(Existencia::count())->toBeGreaterThan(0)
        ->and(Movimiento::count())->toBeGreaterThan(0);

    $this->seed(AlmResetSeeder::class);

    expect(Almacen::count())->toBe(0)
        ->and(Ubicacion::count())->toBe(0)
        ->and(Area::count())->toBe(0)
        ->and(Existencia::count())->toBe(0)
        ->and(Movimiento::count())->toBe(0)
        // Los artículos que dio de alta la carga inicial se van con ella.
        ->and(Producto::count())->toBe(0);
});

/**
 * El consecutivo sale del `MAX` sobre los códigos vivos, así que borrarlos lo
 * devuelve al principio: la siguiente carga vuelve a abrir en ART-00001.
 */
test('el consecutivo de artículos vuelve a empezar', function () {
    $autoriza = User::factory()->create();
    $autoriza->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));

    Almacen::factory()->create(['clave' => 'SOL', 'obra_id' => null]);
    $this->seed(SoldaduraSeeder::class);

    expect(Producto::max('codigo'))->toBe('ART-00011');

    $this->seed(AlmResetSeeder::class);

    Almacen::factory()->create(['clave' => 'SOL', 'obra_id' => null]);
    $this->seed(SoldaduraSeeder::class);

    expect(Producto::min('codigo'))->toBe('ART-00001')
        ->and(Producto::count())->toBe(11);
});

/**
 * Lo que Compras teclea con su propio código no es del almacén: el reset no se
 * lo lleva por delante.
 */
test('respeta los artículos que no dio de alta el almacén', function () {
    $ajeno = Producto::factory()->create(['codigo' => 'TOR-0012']);
    $sinCodigo = Producto::factory()->create(['codigo' => null, 'controla_inventario' => false]);

    $this->seed(AlmResetSeeder::class);

    expect(Producto::whereIn('id', [$ajeno->id, $sinCodigo->id])->count())->toBe(2);
});

/**
 * Los enlaces de Costos hacia acá son `restrictOnDelete`: si no se apagan
 * primero, el borrado se bloquea. El renglón sobrevive sin producto porque
 * guarda su propia descripción.
 */
test('desengancha lo que Costos apuntaba al almacén sin borrarlo', function () {
    $almacen = Almacen::factory()->create();
    $producto = Producto::factory()->create(['codigo' => 'ART-00001']);

    $entrega = Entrega::factory()->create(['almacen_id' => $almacen->id]);
    $renglon = EntregaDetalle::factory()->create([
        'entrega_id' => $entrega->id,
        'producto_id' => $producto->id,
        'descripcion' => 'SOLDADURA DE ALAMBRE 0.35',
    ]);

    $orden = OrdenCompra::factory()->create();
    $partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $orden->id,
        'producto_id' => $producto->id,
    ]);

    $this->seed(AlmResetSeeder::class);

    expect(Entrega::find($entrega->id))->not->toBeNull()
        ->and(Entrega::find($entrega->id)->almacen_id)->toBeNull()
        ->and(EntregaDetalle::find($renglon->id)->producto_id)->toBeNull()
        ->and(EntregaDetalle::find($renglon->id)->descripcion)->toBe('SOLDADURA DE ALAMBRE 0.35')
        ->and(OrdenCompraDetalle::find($partida->id)->producto_id)->toBeNull()
        ->and(OrdenCompra::find($orden->id))->not->toBeNull();
});

test('se puede correr sobre un módulo vacío', function () {
    $this->seed(AlmResetSeeder::class);

    expect(Almacen::count())->toBe(0)
        ->and(Producto::count())->toBe(0);
});
