<?php

use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Pago;
use App\Models\Proveedor;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'costos.facturas.aprobar', 'guard_name' => 'web']);
});

function crearFacturaPendienteAprobacion(): Factura
{
    $proveedor = Proveedor::factory()->create();

    $oc = OrdenCompra::factory()->pendienteEntrega()->create([
        'proveedor_id' => $proveedor->id,
    ]);

    $factura = Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $proveedor->id,
        'estatus' => 'pendiente_aprobacion',
    ]);

    Entrega::factory()->create([
        'orden_compra_id' => $factura->orden_compra_id,
        'tipo' => 'completa',
    ]);

    return $factura;
}

test('aprueba factura con entrega completa y cambia a pendiente_pago', function () {
    $factura = crearFacturaPendienteAprobacion();

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/aprobar-costos")
        ->assertRedirect();

    $factura->refresh();
    expect($factura->aprobada_costos)->toBeTrue();
    expect($factura->aprobada_costos_por)->toBe($this->user->id);
    expect($factura->aprobada_costos_at)->not->toBeNull();
    expect($factura->estatus->value)->toBe('pendiente_pago');

    expect(Pago::where('pagable_type', Factura::class)->where('pagable_id', $factura->id)->count())->toBe(0);
});

test('no aprueba factura en pendiente_entrega', function () {
    $proveedor = Proveedor::factory()->create();
    $oc = OrdenCompra::factory()->pendienteEntrega()->create(['proveedor_id' => $proveedor->id]);
    $factura = Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $proveedor->id,
        'estatus' => 'pendiente_entrega',
    ]);

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/aprobar-costos")
        ->assertSessionHasErrors('estatus');

    $factura->refresh();
    expect($factura->aprobada_costos)->toBeFalse();
});

test('no aprueba factura con estatus pendiente_pago', function () {
    $factura = Factura::factory()->pendientePago()->create();

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/aprobar-costos")
        ->assertSessionHasErrors('estatus');
});

test('no aprueba factura ya aprobada', function () {
    $factura = crearFacturaPendienteAprobacion();
    $factura->update([
        'aprobada_costos' => true,
        'aprobada_costos_por' => $this->user->id,
        'aprobada_costos_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/aprobar-costos")
        ->assertSessionHasErrors('aprobada_costos');
});

test('aprobacion recalcula OC a pendiente_pago', function () {
    $factura = crearFacturaPendienteAprobacion();

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/aprobar-costos")
        ->assertRedirect();

    $factura->refresh();
    $factura->ordenCompra->refresh();
    expect($factura->ordenCompra->estatus->value)->toBe('pendiente_pago');
});
