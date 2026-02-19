<?php

use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Proveedor;

beforeEach(function () {
    $this->proveedor = Proveedor::factory()->create([
        'email' => 'proveedor@test.com',
        'password' => bcrypt('password'),
        'tiene_acceso_portal' => true,
        'activo' => true,
    ]);
});

test('lista facturas del proveedor', function () {
    Factura::factory()->count(2)->create(['proveedor_id' => $this->proveedor->id]);
    // Factura de otro proveedor
    Factura::factory()->create();

    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal/facturas')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('portal/facturas/index')
            ->has('facturas.data', 2)
        );
});

test('sube factura a orden propia', function () {
    $oc = OrdenCompra::factory()->aprobada()->create(['proveedor_id' => $this->proveedor->id]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas', [
            'orden_compra_id' => $oc->id,
            'subtotal' => 10000,
            'iva' => 1600,
            'total' => 11600,
            'fecha_factura' => '2026-02-17',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('costos_facturas', [
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $this->proveedor->id,
    ]);
});

test('no puede subir factura a orden de otro proveedor', function () {
    $oc = OrdenCompra::factory()->aprobada()->create();

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas', [
            'orden_compra_id' => $oc->id,
            'subtotal' => 10000,
            'iva' => 1600,
            'total' => 11600,
        ])
        ->assertForbidden();
});

test('muestra detalle de factura propia', function () {
    $factura = Factura::factory()->create(['proveedor_id' => $this->proveedor->id]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get("/portal/facturas/{$factura->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('portal/facturas/show')
            ->where('factura.id', $factura->id)
        );
});

test('no puede ver factura de otro proveedor', function () {
    $factura = Factura::factory()->create();

    $this->actingAs($this->proveedor, 'proveedor')
        ->get("/portal/facturas/{$factura->id}")
        ->assertForbidden();
});

test('valida campos requeridos al subir factura', function () {
    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas', [])
        ->assertSessionHasErrors(['orden_compra_id', 'subtotal', 'iva', 'total']);
});
