<?php

use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'costos.facturas.ver', 'guard_name' => 'web']);
});

test('lista facturas', function () {
    Factura::factory()->count(3)->create();

    $this->actingAs($this->user)
        ->get('/admin/costos/facturas')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/costos/facturas/index')
            ->has('facturas.data', 3)
        );
});

test('filtra facturas por estatus', function () {
    Factura::factory()->create(['estatus' => 'pendiente_entrega']);
    Factura::factory()->create(['estatus' => 'pendiente_aprobacion']);

    $this->actingAs($this->user)
        ->get('/admin/costos/facturas?estatus=pendiente_entrega')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('facturas.data', 1)
        );
});

test('busca facturas por folio', function () {
    $factura = Factura::factory()->create();

    $this->actingAs($this->user)
        ->get("/admin/costos/facturas?search={$factura->folio}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('facturas.data', 1)
        );
});

test('muestra detalle de factura', function () {
    $oc = OrdenCompra::factory()->aprobada()->create();
    $detalle = OrdenCompraDetalle::factory()->create(['orden_compra_id' => $oc->id]);
    $factura = Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $oc->proveedor_id,
    ]);

    $this->actingAs($this->user)
        ->get("/admin/costos/facturas/{$factura->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/costos/facturas/show')
            ->has('factura')
            ->where('factura.id', $factura->id)
        );
});

test('genera folio automatico para factura', function () {
    $factura = Factura::factory()->create();

    expect($factura->folio)->toStartWith('FA-');
    expect(strlen($factura->folio))->toBeGreaterThan(6);
});

test('factura tiene relacion con pago morph', function () {
    $factura = Factura::factory()->create();

    expect($factura->pago)->toBeNull();

    $factura->pago()->create([
        'monto_pago' => $factura->total,
        'moneda' => 'mxn',
        'tipo_pago' => 'contado',
        'estatus' => 'pendiente',
    ]);

    $factura->refresh();
    expect($factura->pago)->not->toBeNull();
    expect((float) $factura->pago->monto_pago)->toBe((float) $factura->total);
});
