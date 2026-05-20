<?php

use App\Models\Costos\Entrega;
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

function ocConRecepcion(?int $proveedorId = null): OrdenCompra
{
    $oc = OrdenCompra::factory()->pendienteFactura()->create(
        $proveedorId ? ['proveedor_id' => $proveedorId] : []
    );
    Entrega::factory()->create(['orden_compra_id' => $oc->id]);

    return $oc;
}

test('lista facturas del proveedor', function () {
    Factura::factory()->count(2)->create(['proveedor_id' => $this->proveedor->id]);
    Factura::factory()->create();

    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal/facturas')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('portal/facturas/index')
            ->has('facturas.data', 2)
        );
});

test('sube factura a orden propia con recepcion registrada', function () {
    $oc = ocConRecepcion($this->proveedor->id);

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
        'estatus' => 'pendiente_aprobacion',
    ]);
});

test('bloquea factura cuando la OC no tiene recepción de almacén', function () {
    $oc = OrdenCompra::factory()->pendienteFactura()->create(['proveedor_id' => $this->proveedor->id]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas', [
            'orden_compra_id' => $oc->id,
            'total' => 1000,
        ])
        ->assertSessionHasErrors(['orden_compra_id']);

    expect(Factura::where('orden_compra_id', $oc->id)->count())->toBe(0);
});

test('no puede subir factura a orden de otro proveedor', function () {
    $oc = ocConRecepcion();

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
        ->assertSessionHasErrors(['orden_compra_id', 'total']);
});

test('rechaza factura con uuid_fiscal duplicado', function () {
    $uuid = '12345678-1234-1234-1234-123456789012';

    Factura::factory()->create([
        'proveedor_id' => $this->proveedor->id,
        'uuid_fiscal' => $uuid,
    ]);

    $oc = ocConRecepcion($this->proveedor->id);

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas', [
            'orden_compra_id' => $oc->id,
            'uuid_fiscal' => $uuid,
            'total' => 5000,
        ])
        ->assertSessionHasErrors(['uuid_fiscal']);
});

test('permite multiples facturas sin uuid_fiscal', function () {
    $ocA = ocConRecepcion($this->proveedor->id);
    $ocB = ocConRecepcion($this->proveedor->id);

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas', [
            'orden_compra_id' => $ocA->id,
            'total' => 1000,
        ])
        ->assertRedirect();

    $this->actingAs($this->proveedor, 'proveedor')
        ->post('/portal/facturas', [
            'orden_compra_id' => $ocB->id,
            'total' => 2000,
        ])
        ->assertRedirect();

    expect(Factura::where('proveedor_id', $this->proveedor->id)->count())->toBe(2);
});
