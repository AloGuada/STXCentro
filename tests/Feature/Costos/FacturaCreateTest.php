<?php

use App\Models\Costos\Factura;
use App\Models\Costos\FacturaDetalle;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'costos.facturas.crear', 'guard_name' => 'web']);
    $this->user->givePermissionTo('costos.facturas.crear');
});

function ocConPartidas(array $cantidades = [10, 5]): OrdenCompra
{
    $oc = OrdenCompra::factory()->pendienteFactura()->create(['total' => 0]);
    $total = 0;
    foreach ($cantidades as $c) {
        $precio = 100;
        $sub = $c * $precio;
        $total += $sub;
        OrdenCompraDetalle::factory()->create([
            'orden_compra_id' => $oc->id,
            'cantidad' => $c,
            'precio_unitario' => $precio,
            'subtotal' => $sub,
        ]);
    }
    $oc->update(['total' => $total]);

    return $oc->load('detalles');
}

test('admin crea factura con partidas que respetan el saldo facturable', function () {
    $oc = ocConPartidas([10, 5]);
    $p1 = $oc->detalles[0];
    $p2 = $oc->detalles[1];

    $this->actingAs($this->user)
        ->post('/admin/costos/facturas', [
            'orden_compra_id' => $oc->id,
            'moneda' => 'mxn',
            'iva' => 160,
            'detalles' => [
                ['orden_compra_detalle_id' => $p1->id, 'cantidad' => 4, 'precio_unitario' => 100],
                ['orden_compra_detalle_id' => $p2->id, 'cantidad' => 2, 'precio_unitario' => 100],
            ],
        ])
        ->assertRedirect();

    $factura = Factura::first();
    expect($factura)->not->toBeNull();
    expect($factura->orden_compra_id)->toBe($oc->id);
    expect((float) $factura->subtotal)->toBe(600.0);
    expect((float) $factura->iva)->toBe(160.0);
    expect((float) $factura->total)->toBe(760.0);
    expect($factura->estatus->value)->toBe('pendiente_aprobacion');

    $detalles = FacturaDetalle::where('factura_id', $factura->id)->get();
    expect($detalles)->toHaveCount(2);
    expect((float) $detalles->firstWhere('orden_compra_detalle_id', $p1->id)->subtotal)->toBe(400.0);
});

test('rechaza factura con cantidad que excede el saldo facturable por partida', function () {
    $oc = ocConPartidas([10]);
    $p1 = $oc->detalles[0];

    $this->actingAs($this->user)
        ->post('/admin/costos/facturas', [
            'orden_compra_id' => $oc->id,
            'moneda' => 'mxn',
            'detalles' => [
                ['orden_compra_detalle_id' => $p1->id, 'cantidad' => 15, 'precio_unitario' => 100],
            ],
        ])
        ->assertSessionHasErrors('detalles.0.cantidad');

    expect(Factura::count())->toBe(0);
});

test('rechaza factura cuando el acumulado entre facturas activas excede la OC', function () {
    $oc = ocConPartidas([10]);
    $p1 = $oc->detalles[0];

    $f1 = Factura::factory()->create(['orden_compra_id' => $oc->id, 'estatus' => 'pendiente_aprobacion']);
    FacturaDetalle::factory()->create([
        'factura_id' => $f1->id,
        'orden_compra_detalle_id' => $p1->id,
        'cantidad' => 7,
        'precio_unitario' => 100,
        'subtotal' => 700,
    ]);

    $this->actingAs($this->user)
        ->post('/admin/costos/facturas', [
            'orden_compra_id' => $oc->id,
            'moneda' => 'mxn',
            'detalles' => [
                ['orden_compra_detalle_id' => $p1->id, 'cantidad' => 5, 'precio_unitario' => 100],
            ],
        ])
        ->assertSessionHasErrors('detalles.0.cantidad');
});

test('factura cancelada no cuenta en el saldo facturable', function () {
    $oc = ocConPartidas([10]);
    $p1 = $oc->detalles[0];

    $f1 = Factura::factory()->create(['orden_compra_id' => $oc->id, 'estatus' => 'cancelada']);
    FacturaDetalle::factory()->create([
        'factura_id' => $f1->id,
        'orden_compra_detalle_id' => $p1->id,
        'cantidad' => 7,
        'precio_unitario' => 100,
        'subtotal' => 700,
    ]);

    $this->actingAs($this->user)
        ->post('/admin/costos/facturas', [
            'orden_compra_id' => $oc->id,
            'moneda' => 'mxn',
            'detalles' => [
                ['orden_compra_detalle_id' => $p1->id, 'cantidad' => 10, 'precio_unitario' => 100],
            ],
        ])
        ->assertRedirect();

    expect(Factura::where('estatus', 'pendiente_aprobacion')->count())->toBe(1);
});

test('rechaza factura si una partida no pertenece a la OC', function () {
    $oc = ocConPartidas([10]);
    $otra = OrdenCompraDetalle::factory()->create();

    $this->actingAs($this->user)
        ->post('/admin/costos/facturas', [
            'orden_compra_id' => $oc->id,
            'moneda' => 'mxn',
            'detalles' => [
                ['orden_compra_detalle_id' => $otra->id, 'cantidad' => 1, 'precio_unitario' => 50],
            ],
        ])
        ->assertSessionHasErrors('detalles.0.orden_compra_detalle_id');
});

test('sin permiso costos.facturas.crear retorna 403', function () {
    $oc = ocConPartidas([10]);
    $otro = User::factory()->create();

    $this->actingAs($otro)
        ->post('/admin/costos/facturas', [
            'orden_compra_id' => $oc->id,
            'moneda' => 'mxn',
            'detalles' => [
                ['orden_compra_detalle_id' => $oc->detalles[0]->id, 'cantidad' => 1, 'precio_unitario' => 100],
            ],
        ])
        ->assertForbidden();
});

test('uuid_fiscal duplicado es rechazado', function () {
    $oc = ocConPartidas([10]);
    Factura::factory()->create(['uuid_fiscal' => 'DUP-UUID-XYZ']);

    $this->actingAs($this->user)
        ->post('/admin/costos/facturas', [
            'orden_compra_id' => $oc->id,
            'uuid_fiscal' => 'DUP-UUID-XYZ',
            'moneda' => 'mxn',
            'detalles' => [
                ['orden_compra_detalle_id' => $oc->detalles[0]->id, 'cantidad' => 1, 'precio_unitario' => 100],
            ],
        ])
        ->assertSessionHasErrors('uuid_fiscal');
});
