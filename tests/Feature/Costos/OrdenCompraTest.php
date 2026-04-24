<?php

use App\Models\Costos\Factura;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'costos.ordenes-compra.ver', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'costos.ordenes-compra.crear', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'costos.ordenes-compra.cancelar', 'guard_name' => 'web']);
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

test('crea orden de compra pendiente_factura y aplica impacto presupuestal', function () {
    $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $oc = OrdenCompra::factory()->make();

    $this->actingAs($this->user)
        ->post('/admin/costos/ordenes-compra', [
            'proveedor_id' => $oc->proveedor_id,
            'departamento_id' => $oc->departamento_id,
            'moneda' => 'mxn',
            'total' => 5000,
            'detalles' => [
                [
                    'obra_rubro_id' => $obraRubro->id,
                    'monto' => 5000,
                ],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseCount('costos_ordenes_compra', 1);
    $this->assertDatabaseCount('costos_ordenes_compra_detalle', 1);

    $created = OrdenCompra::first();
    expect($created->folio)->toStartWith('OC-');
    expect($created->estatus->value)->toBe('pendiente_factura');
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
        'monto' => 5000,
    ]);

    $this->actingAs($this->user)
        ->post("/admin/costos/ordenes-compra/{$oc->id}/cancelar")
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
        ->post("/admin/costos/ordenes-compra/{$oc->id}/cancelar")
        ->assertSessionHasErrors('estatus');
});

test('no permite cancelar sin permiso', function () {
    $oc = OrdenCompra::factory()->create(['creado_por' => $this->user->id]);

    $this->actingAs($this->user)
        ->post("/admin/costos/ordenes-compra/{$oc->id}/cancelar")
        ->assertForbidden();
});

test('elimina una orden sin facturas y revierte impacto', function () {
    $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 5000]);
    $oc = OrdenCompra::factory()->create(['creado_por' => $this->user->id, 'total' => 5000, 'estatus' => 'pendiente_factura']);
    OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'obra_rubro_id' => $obraRubro->id,
        'monto' => 5000,
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
