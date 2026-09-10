<?php

use App\Models\Alm\Almacen;
use App\Models\Costos\Entrega;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Proveedor;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * Las pantallas de entradas leen del proveedor y de la orden de compra. Los
 * datos de ejemplo del módulo (AlmEntradaDevSeeder) pasan por aquí: basta una
 * recepción contra orden para que las tres consultas se ejecuten de verdad.
 */
beforeEach(function () {
    foreach (['alm.entradas.ver', 'alm.entradas.crear', 'alm.almacenes.ver-todos'] as $permName) {
        Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
    }

    $this->almacenista = User::factory()->create();
    $this->almacenista->givePermissionTo(['alm.entradas.ver', 'alm.entradas.crear', 'alm.almacenes.ver-todos']);

    $this->almacen = Almacen::factory()->create();
    $proveedor = Proveedor::factory()->create([
        'razon_social' => 'Aceros y Perfiles del Bajío SA de CV',
        'nombre_comercial' => 'Aceros del Bajío',
    ]);

    $this->orden = OrdenCompra::factory()->create(['proveedor_id' => $proveedor->id]);
    $partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $this->orden->id,
        'cantidad' => 100,
        'precio_unitario' => 45,
    ]);

    $this->entrada = Entrega::factory()->create([
        'orden_compra_id' => $this->orden->id,
        'almacen_id' => $this->almacen->id,
        'recibido_por' => $this->almacenista->id,
    ]);
    $this->entrada->detalles()->create([
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 60,
    ]);
});

test('el listado de entradas muestra la recepción con el nombre del proveedor', function () {
    $this->actingAs($this->almacenista)
        ->get('/admin/almacen/entradas')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('entradas.data.0.proveedor', 'Aceros del Bajío')
            ->where('entradas.data.0.orden_folio', $this->orden->folio)
            // La orden todavía debe 40 piezas, así que cuenta como abierta. El
            // listado sólo anuncia cuántas hay; elegirlas es de la captura.
            ->where('ordenesAbiertasCount', 1)
        );
});

test('el detalle de la entrada carga con su orden de compra', function () {
    $this->actingAs($this->almacenista)
        ->get("/admin/almacen/entradas/{$this->entrada->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('entrada.proveedor', 'Aceros del Bajío'));
});

test('la captura de entrada sin orden lista proveedores y órdenes abiertas', function () {
    $this->actingAs($this->almacenista)
        ->get('/admin/almacen/entradas/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('proveedores.0.nombre', 'Aceros del Bajío'));
});

test('la orden totalmente recibida deja de aparecer como abierta', function () {
    $this->entrada->detalles()->first()->update(['cantidad_recibida' => 100]);

    $this->actingAs($this->almacenista)
        ->get('/admin/almacen/entradas')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('ordenesAbiertasCount', 0));
});
