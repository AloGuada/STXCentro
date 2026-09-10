<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\Producto;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\UsoCfdi;
use App\Models\Departamento;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * El catálogo `costos_productos` lo comparten Compras y Almacén, pero desde el
 * 2026-09-09 **sólo se escribe desde Almacén > Artículos**. La partida tecleada
 * al vuelo en la requisición ya no estrena productos: un producto nacido ahí
 * no decía si era insumo o activo, y la herramienta terminaba en el kardex
 * como consumible. Lo que no está en el catálogo se rechaza con un mensaje que
 * manda a darlo de alta donde sí se decide qué es.
 */
beforeEach(function () {
    foreach (['costos.requisiciones.ver', 'costos.requisiciones.crear'] as $permName) {
        Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['costos.requisiciones.ver', 'costos.requisiciones.crear']);

    $this->depto = Departamento::factory()->create();
    $this->presupuesto = Presupuesto::factory()->paraObra()->create();
    $this->rubro = ObraRubro::factory()->create(['presupuesto_id' => $this->presupuesto->id]);
    $this->uso = UsoCfdi::factory()->create();
});

/**
 * @param  list<array<string, mixed>>  $detalles
 */
function requisicionCon(array $detalles): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => test()->depto->id,
            'presupuesto_id' => test()->presupuesto->id,
            'detalles' => array_map(fn (array $d): array => [
                'cantidad' => 10,
                'obra_rubro_id' => test()->rubro->id,
                'uso_cfdi_id' => test()->uso->id,
                ...$d,
            ], $detalles),
        ]);
}

test('una partida tecleada al vuelo ya no estrena producto: se rechaza', function () {
    requisicionCon([['descripcion' => 'Tornillo que nadie clasifico', 'unidad' => 'pza']])
        ->assertSessionHasErrors(['detalles.0.producto_id']);

    expect(Producto::where('descripcion', 'Tornillo que nadie clasifico')->exists())->toBeFalse()
        ->and(Requisicion::count())->toBe(0);
});

test('ni con código: el código no es puerta de alta', function () {
    requisicionCon([['descripcion' => 'Tornillo A325 3/4"', 'unidad' => 'pza', 'codigo_producto' => 'TOR-0012']])
        ->assertSessionHasErrors(['detalles.0.producto_id']);

    expect(Producto::where('codigo', 'TOR-0012')->exists())->toBeFalse();
});

test('la partida del catálogo entra con los datos del producto, no con los tecleados', function () {
    $producto = Producto::factory()->create(['descripcion' => 'Electrodo 7018', 'unidad' => 'kg', 'codigo' => 'ELE-7018']);

    requisicionCon([['producto_id' => $producto->id, 'descripcion' => 'lo que sea', 'unidad' => 'pza']])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $detalle = RequisicionDetalle::sole();

    expect($detalle->producto_id)->toBe($producto->id)
        ->and($detalle->descripcion)->toBe('Electrodo 7018')
        ->and($detalle->unidad)->toBe('kg')
        ->and($detalle->codigo_producto)->toBe('ELE-7018')
        ->and(Producto::count())->toBe(1);
});

test('el mismo producto en dos partidas lo comparten sin duplicarlo', function () {
    $producto = Producto::factory()->create(['descripcion' => 'Mismo insumo', 'unidad' => 'pza']);

    requisicionCon([
        ['producto_id' => $producto->id, 'descripcion' => 'Mismo insumo', 'unidad' => 'pza', 'cantidad' => 5],
        ['producto_id' => $producto->id, 'descripcion' => 'Mismo insumo', 'unidad' => 'pza', 'cantidad' => 3],
    ])->assertRedirect();

    expect(Producto::count())->toBe(1)
        ->and(RequisicionDetalle::where('producto_id', $producto->id)->count())->toBe(2);
});
