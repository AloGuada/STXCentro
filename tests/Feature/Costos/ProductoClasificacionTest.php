<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\Producto;
use App\Models\Costos\UsoCfdi;
use App\Models\Departamento;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * El catálogo `costos_productos` lo comparten Compras y Almacén, y Compras lo
 * puede escribir tecleando una descripción libre. Esa libertad es deliberada
 * (ver `resolverProducto`: se probó deduplicar y se descartó), pero el kardex no
 * puede montarse encima de renglones que nadie clasificó: dos códigos para el
 * mismo tornillo son dos saldos que nunca cuadran.
 *
 * La frontera es el código. Con él, Compras dio de alta algo deliberado y entra
 * al inventario; sin él, el producto nace fuera y sólo entra cuando Almacén lo
 * clasifica desde la pantalla de Artículos.
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

function crearRequisicionCon(array $detalle): void
{
    test()->actingAs(test()->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => test()->depto->id,
            'presupuesto_id' => test()->presupuesto->id,
            'detalles' => [[
                'cantidad' => 10,
                'obra_rubro_id' => test()->rubro->id,
                'uso_cfdi_id' => test()->uso->id,
                ...$detalle,
            ]],
        ])
        ->assertRedirect();
}

test('un producto tecleado sin codigo nace fuera del inventario', function () {
    crearRequisicionCon(['descripcion' => 'Tornillo que nadie clasifico', 'unidad' => 'pza']);

    $producto = Producto::firstWhere('descripcion', 'Tornillo que nadie clasifico');

    expect($producto)->not->toBeNull()
        ->and($producto->codigo)->toBeNull()
        ->and($producto->controla_inventario)->toBeFalse();

    expect(Producto::sinClasificar()->pluck('id'))->toContain($producto->id);
    expect(Producto::deInventario()->pluck('id'))->not->toContain($producto->id);
});

test('un producto tecleado con codigo entra al inventario', function () {
    crearRequisicionCon([
        'descripcion' => 'Tornillo A325 3/4"',
        'unidad' => 'pza',
        'codigo_producto' => 'TOR-0012',
    ]);

    $producto = Producto::firstWhere('codigo', 'TOR-0012');

    expect($producto)->not->toBeNull()
        ->and($producto->controla_inventario)->toBeTrue();

    expect(Producto::deInventario()->pluck('id'))->toContain($producto->id);
});

test('un codigo en blanco cuenta como sin codigo', function () {
    crearRequisicionCon([
        'descripcion' => 'Material con codigo vacio',
        'unidad' => 'pza',
        'codigo_producto' => '   ',
    ]);

    $producto = Producto::firstWhere('descripcion', 'Material con codigo vacio');

    expect($producto->codigo)->toBeNull()
        ->and($producto->controla_inventario)->toBeFalse();
});

test('el tipo por defecto es insumo y no se controla por pieza', function () {
    crearRequisicionCon(['descripcion' => 'Electrodo nuevo', 'unidad' => 'kg', 'codigo_producto' => 'ELE-7018']);

    $producto = Producto::firstWhere('codigo', 'ELE-7018');

    expect($producto->tipo)->toBe(App\Enums\Alm\ProductoTipo::Insumo)
        ->and($producto->se_controla_por_pieza)->toBeFalse()
        ->and($producto->requiere_verificacion)->toBeFalse()
        ->and($producto->stock_minimo)->toBeNull();
});

/**
 * Capturar el mismo insumo nuevo en dos partidas sigue creando dos productos: no
 * se deduplica al crear (decisión 2026-07-28), se limpia después con
 * `costos:limpiar-productos`. Este test fija esa decisión para que un refactor
 * no la revierta por descuido.
 */
test('no se deduplica al crear', function () {
    test()->actingAs(test()->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => test()->depto->id,
            'presupuesto_id' => test()->presupuesto->id,
            'detalles' => [
                ['descripcion' => 'Mismo insumo', 'unidad' => 'pza', 'cantidad' => 5, 'obra_rubro_id' => test()->rubro->id, 'uso_cfdi_id' => test()->uso->id],
                ['descripcion' => 'Mismo insumo', 'unidad' => 'pza', 'cantidad' => 3, 'obra_rubro_id' => test()->rubro->id, 'uso_cfdi_id' => test()->uso->id],
            ],
        ])
        ->assertRedirect();

    expect(Producto::where('descripcion', 'Mismo insumo')->count())->toBe(2);
});
