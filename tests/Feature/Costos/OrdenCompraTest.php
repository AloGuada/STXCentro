<?php

use App\Exports\Costos\OrdenesCompraExport;
use App\Models\Costos\Factura;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['ver', 'ver-todas', 'crear', 'cancelar'] as $accion) {
        Permission::firstOrCreate(['name' => "costos.ordenes-compra.{$accion}", 'guard_name' => 'web']);
    }
    // ver-todas: el index filtra por dueño salvo que el usuario pueda ver todas.
    $this->user->givePermissionTo('costos.ordenes-compra.ver-todas');
});

test('lista ordenes de compra', function () {
    OrdenCompra::factory()->count(3)->create();

    $this->actingAs($this->user)
        ->get('/admin/costos/ordenes-compra')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/costos/ordenes-compra/index')
            ->has('ordenes.data', 3)
        );
});

test('el export aplana una linea por producto de la OC', function () {
    $oc = OrdenCompra::factory()->create();
    OrdenCompraDetalle::factory()->count(2)->create(['orden_compra_id' => $oc->id]);
    $otra = OrdenCompra::factory()->create();
    OrdenCompraDetalle::factory()->create(['orden_compra_id' => $otra->id]);

    $filas = (new OrdenesCompraExport([]))->collection();

    // 2 + 1 = 3 líneas, una por producto, repitiendo los datos de la OC.
    expect($filas)->toHaveCount(3);
    expect($filas->first())->toHaveKeys(['folio', 'estatus', 'proveedor', 'obra', 'tipo_pago', 'total', 'producto', 'cantidad', 'subtotal']);
});

test('una OC sin productos exporta una sola linea', function () {
    OrdenCompra::factory()->create();

    expect((new OrdenesCompraExport([]))->collection())->toHaveCount(1);
});

test('exporta el listado de OC a excel', function () {
    OrdenCompra::factory()->create();

    $res = $this->actingAs($this->user)->get('/admin/costos/ordenes-compra/exportar');

    $res->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('.xlsx');
});

test('el PDF de la OC se genera con requisición y uso CFDI', function () {
    $oc = OrdenCompra::factory()->create();
    OrdenCompraDetalle::factory()->create(['orden_compra_id' => $oc->id]);

    $res = $this->actingAs($this->user)->get("/admin/costos/ordenes-compra/{$oc->id}/pdf-oc");

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('pdf');
});

test('el show expone las solicitudes de pago ligadas a la OC', function () {
    $oc = OrdenCompra::factory()->pendienteEntrega()->create();
    \App\Models\Costos\SolicitudPago::factory()->create(['orden_compra_id' => $oc->id]);

    $this->actingAs($this->user)
        ->get("/admin/costos/ordenes-compra/{$oc->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/costos/ordenes-compra/show')
            ->has('ordenCompra.solicitudes_pago', 1)
        );
});

test('muestra formulario de creacion', function () {
    $this->actingAs($this->user)
        ->get('/admin/costos/ordenes-compra/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/costos/ordenes-compra/create')
            ->has('proveedores')
            ->has('obras')
            ->has('departamentos')
            ->has('obraRubros')
        );
});

test('crea orden de compra pendiente_entrega y aplica impacto presupuestal', function () {
    $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $oc = OrdenCompra::factory()->make();

    $this->actingAs($this->user)
        ->post('/admin/costos/ordenes-compra', [
            'proveedor_id' => $oc->proveedor_id,
            'departamento_id' => $oc->departamento_id,
            'moneda' => 'mxn',
            'fecha_entrega_esperada' => now()->addDays(7)->format('Y-m-d'),
            'total' => 5000,
            'detalles' => [
                [
                    'obra_rubro_id' => $obraRubro->id,
                    'descripcion' => 'Cemento gris',
                    'unidad' => 'bulto',
                    'cantidad' => 10,
                    'precio_unitario' => 500,
                ],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseCount('costos_ordenes_compra', 1);
    $this->assertDatabaseCount('costos_ordenes_compra_detalle', 1);

    $created = OrdenCompra::first();
    expect($created->folio)->toStartWith('OC-');
    expect($created->estatus->value)->toBe('pendiente_entrega');
    expect((float) $created->total)->toBe(5000.0);

    $obraRubro->refresh();
    expect((float) $obraRubro->acumulado)->toBe(5000.0);
});

test('genera folio automatico', function () {
    $oc = OrdenCompra::factory()->create();

    expect($oc->folio)->toStartWith('OC-');
    expect(strlen($oc->folio))->toBeGreaterThan(6);
});

test('cancela una orden pendiente y revierte impacto', function () {
    $this->user->givePermissionTo('costos.ordenes-compra.cancelar');

    $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 5000]);
    $oc = OrdenCompra::factory()->create(['creado_por' => $this->user->id, 'total' => 5000, 'estatus' => 'pendiente_factura']);
    OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'obra_rubro_id' => $obraRubro->id,
        'cantidad' => 1,
        'precio_unitario' => 5000,
        'subtotal' => 5000,
    ]);

    $this->actingAs($this->user)
        ->post("/admin/costos/ordenes-compra/{$oc->id}/cancelar", ['motivo' => 'Cancelación motivada por test'])
        ->assertRedirect();

    $oc->refresh();
    expect($oc->estatus->value)->toBe('cancelada');

    $obraRubro->refresh();
    expect((float) $obraRubro->acumulado)->toBe(0.0);
});

test('no permite cancelar orden en pendiente_pago', function () {
    $this->user->givePermissionTo('costos.ordenes-compra.cancelar');

    $oc = OrdenCompra::factory()->create(['creado_por' => $this->user->id, 'estatus' => 'pendiente_pago']);

    $this->actingAs($this->user)
        ->post("/admin/costos/ordenes-compra/{$oc->id}/cancelar", ['motivo' => 'Cancelación motivada por test'])
        ->assertSessionHasErrors('estatus');
});

test('no permite cancelar sin permiso', function () {
    $oc = OrdenCompra::factory()->create(['creado_por' => $this->user->id]);

    $this->actingAs($this->user)
        ->post("/admin/costos/ordenes-compra/{$oc->id}/cancelar", ['motivo' => 'Cancelación motivada por test'])
        ->assertForbidden();
});

test('elimina una orden sin facturas y revierte impacto', function () {
    $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 5000]);
    $oc = OrdenCompra::factory()->create(['creado_por' => $this->user->id, 'total' => 5000, 'estatus' => 'pendiente_factura']);
    OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'obra_rubro_id' => $obraRubro->id,
        'cantidad' => 1,
        'precio_unitario' => 5000,
        'subtotal' => 5000,
    ]);

    $this->actingAs($this->user)
        ->delete("/admin/costos/ordenes-compra/{$oc->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('costos_ordenes_compra', ['id' => $oc->id]);

    $obraRubro->refresh();
    expect((float) $obraRubro->acumulado)->toBe(0.0);
});

test('no permite eliminar orden con facturas', function () {
    $oc = OrdenCompra::factory()->create(['creado_por' => $this->user->id]);
    Factura::factory()->create(['orden_compra_id' => $oc->id, 'proveedor_id' => $oc->proveedor_id]);

    $this->actingAs($this->user)
        ->delete("/admin/costos/ordenes-compra/{$oc->id}")
        ->assertSessionHasErrors('estatus');
});

test('calcula subtotal = cantidad * precio_unitario por partida', function () {
    $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $oc = OrdenCompra::factory()->make();

    $this->actingAs($this->user)
        ->post('/admin/costos/ordenes-compra', [
            'proveedor_id' => $oc->proveedor_id,
            'departamento_id' => $oc->departamento_id,
            'moneda' => 'mxn',
            'fecha_entrega_esperada' => now()->addDays(7)->format('Y-m-d'),
            'total' => 3750,
            'detalles' => [
                [
                    'obra_rubro_id' => $obraRubro->id,
                    'descripcion' => 'Varilla corrugada 3/8"',
                    'unidad' => 'pza',
                    'cantidad' => 15,
                    'precio_unitario' => 250,
                ],
            ],
        ])
        ->assertRedirect();

    $detalle = OrdenCompraDetalle::first();
    expect((float) $detalle->cantidad)->toBe(15.0);
    expect((float) $detalle->precio_unitario)->toBe(250.0);
    expect((float) $detalle->subtotal)->toBe(3750.0);
    expect($detalle->descripcion)->toBe('Varilla corrugada 3/8"');
    expect($detalle->unidad)->toBe('pza');

    // Impacto presupuestal usa subtotal, no cantidad ni precio_unitario sueltos
    $obraRubro->refresh();
    expect((float) $obraRubro->acumulado)->toBe(3750.0);
});

test('validation requiere descripcion, unidad, cantidad y precio_unitario por partida', function () {
    $obraRubro = ObraRubro::factory()->create();
    $oc = OrdenCompra::factory()->make();

    $this->actingAs($this->user)
        ->post('/admin/costos/ordenes-compra', [
            'proveedor_id' => $oc->proveedor_id,
            'departamento_id' => $oc->departamento_id,
            'moneda' => 'mxn',
            'fecha_entrega_esperada' => now()->addDays(7)->format('Y-m-d'),
            'total' => 1000,
            'detalles' => [
                ['obra_rubro_id' => $obraRubro->id],
            ],
        ])
        ->assertSessionHasErrors([
            'detalles.0.descripcion',
            'detalles.0.unidad',
            'detalles.0.cantidad',
            'detalles.0.precio_unitario',
        ]);
});

test('saldo_facturable = total menos facturas activas, excluye canceladas', function () {
    $oc = OrdenCompra::factory()->create(['total' => 10000]);

    Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $oc->proveedor_id,
        'total' => 4000,
        'estatus' => 'pendiente_aprobacion',
    ]);
    Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $oc->proveedor_id,
        'total' => 9999,
        'estatus' => 'cancelada',
    ]);

    $oc->refresh();

    expect($oc->total_facturado)->toBe(4000.0);
    expect($oc->saldo_facturable)->toBe(6000.0);
});
