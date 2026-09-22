<?php

use App\Models\Alm\Almacen;
use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
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

describe('la columna de factura', function () {
    beforeEach(function () {
        $this->factura = Factura::factory()->create([
            'orden_compra_id' => $this->orden->id,
            'folio' => 'FA-2609-0007',
            'folio_fiscal' => 'A-4521',
            'uuid_fiscal' => '11111111-2222-3333-4444-555555555555',
        ]);
        // Las fechas van a mano: el listado ordena por ellas y si no, el orden
        // de los renglones depende de lo que invente el factory.
        $this->entrada->update(['factura_id' => $this->factura->id, 'fecha_entrega' => '2026-09-10']);

        // Una segunda entrada, del mismo almacén, que llegó sin factura.
        $this->sinFactura = Entrega::factory()->create([
            'orden_compra_id' => $this->orden->id,
            'almacen_id' => $this->almacen->id,
            'recibido_por' => $this->almacenista->id,
            'factura_id' => null,
            'fecha_entrega' => '2026-09-11',
        ]);
    });

    test('el listado trae el folio de la factura, y el fiscal manda', function () {
        // El fiscal es el que trae el proveedor en la hoja: es el que se busca.
        $this->actingAs($this->almacenista)
            ->get('/admin/almacen/entradas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('entradas.data.1.factura_id', $this->factura->id)
                ->where('entradas.data.1.factura_folio', 'A-4521')
                ->where('entradas.data.0.factura_id', null)
                ->where('entradas.data.0.factura_folio', null));
    });

    test('se busca la entrada por el folio de su factura', function () {
        $this->actingAs($this->almacenista)
            ->get('/admin/almacen/entradas?search=FA-2609-0007')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('entradas.data', 1)
                ->where('entradas.data.0.id', $this->entrada->id));
    });

    test('también por el uuid fiscal, que es lo que trae el XML', function () {
        $this->actingAs($this->almacenista)
            ->get('/admin/almacen/entradas?search='.$this->factura->uuid_fiscal)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('entradas.data', 1));
    });

    test('buscar por folio de entrada sigue funcionando', function () {
        $this->actingAs($this->almacenista)
            ->get('/admin/almacen/entradas?search='.$this->entrada->folio)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('entradas.data', 1)
                ->where('entradas.data.0.id', $this->entrada->id));
    });

    test('el filtro deja ver sólo las que ya tienen factura', function () {
        $this->actingAs($this->almacenista)
            ->get('/admin/almacen/entradas?factura=con')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('entradas.data', 1)
                ->where('entradas.data.0.id', $this->entrada->id));
    });

    test('y sólo las que llegaron sin ella', function () {
        $this->actingAs($this->almacenista)
            ->get('/admin/almacen/entradas?factura=sin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('entradas.data', 1)
                ->where('entradas.data.0.id', $this->sinFactura->id));
    });
});
