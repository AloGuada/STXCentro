<?php

use App\Models\Costos\Factura;
use App\Models\Costos\Pago;
use App\Models\Proveedor;

beforeEach(function () {
    $this->proveedor = Proveedor::factory()->create([
        'email' => 'proveedor@test.com',
        'password' => bcrypt('password'),
        'tiene_acceso_portal' => true,
        'activo' => true,
    ]);
});

test('lista pagos del proveedor', function () {
    $factura = Factura::factory()->create(['proveedor_id' => $this->proveedor->id]);
    Pago::create([
        'pagable_type' => Factura::class,
        'pagable_id' => $factura->id,
        'monto_pago' => $factura->total,
        'moneda' => 'mxn',
        'tipo_pago' => 'contado',
        'estatus' => 'pendiente',
    ]);

    // Pago de otro proveedor
    $otraFactura = Factura::factory()->create();
    Pago::create([
        'pagable_type' => Factura::class,
        'pagable_id' => $otraFactura->id,
        'monto_pago' => $otraFactura->total,
        'moneda' => 'mxn',
        'tipo_pago' => 'contado',
        'estatus' => 'pendiente',
    ]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal/pagos')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('portal/pagos/index')
            ->has('pagos.data', 1)
        );
});

test('muestra detalle de pago propio', function () {
    $factura = Factura::factory()->create(['proveedor_id' => $this->proveedor->id]);
    $pago = Pago::create([
        'pagable_type' => Factura::class,
        'pagable_id' => $factura->id,
        'monto_pago' => $factura->total,
        'moneda' => 'mxn',
        'tipo_pago' => 'contado',
        'estatus' => 'pendiente',
    ]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get("/portal/pagos/{$pago->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('portal/pagos/show')
            ->where('pago.id', $pago->id)
        );
});

test('no puede ver pago de otro proveedor', function () {
    $factura = Factura::factory()->create();
    $pago = Pago::create([
        'pagable_type' => Factura::class,
        'pagable_id' => $factura->id,
        'monto_pago' => $factura->total,
        'moneda' => 'mxn',
        'tipo_pago' => 'contado',
        'estatus' => 'pendiente',
    ]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get("/portal/pagos/{$pago->id}")
        ->assertForbidden();
});
