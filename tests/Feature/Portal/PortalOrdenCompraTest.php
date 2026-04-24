<?php

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

test('lista ordenes de compra del proveedor', function () {
    OrdenCompra::factory()->aprobada()->count(2)->create(['proveedor_id' => $this->proveedor->id]);
    // Otra OC de otro proveedor
    OrdenCompra::factory()->aprobada()->create();

    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal/ordenes-compra')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('portal/ordenes-compra/index')
            ->has('ordenes.data', 2)
        );
});

test('no muestra ordenes canceladas', function () {
    OrdenCompra::factory()->create([
        'proveedor_id' => $this->proveedor->id,
        'estatus' => 'cancelada',
    ]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal/ordenes-compra')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('ordenes.data', 0)
        );
});

test('muestra detalle de orden propia', function () {
    $oc = OrdenCompra::factory()->aprobada()->create(['proveedor_id' => $this->proveedor->id]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get("/portal/ordenes-compra/{$oc->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('portal/ordenes-compra/show')
            ->where('ordenCompra.id', $oc->id)
        );
});

test('no puede ver orden de otro proveedor', function () {
    $oc = OrdenCompra::factory()->aprobada()->create();

    $this->actingAs($this->proveedor, 'proveedor')
        ->get("/portal/ordenes-compra/{$oc->id}")
        ->assertForbidden();
});

test('requiere autenticacion de proveedor', function () {
    $this->get('/portal/ordenes-compra')
        ->assertRedirect('/portal/login');
});
