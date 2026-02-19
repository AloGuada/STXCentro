<?php

use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Pago;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'costos.entregas.crear', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'costos.facturas.ver', 'guard_name' => 'web']);
});

function crearOcConFactura(): array
{
    $oc = OrdenCompra::factory()->pendienteEntrega()->create();
    $factura = Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $oc->proveedor_id,
        'estatus' => 'pendiente_entrega',
    ]);

    return [$oc, $factura];
}

test('registra entrega y factura sigue pendiente_entrega', function () {
    [$oc, $factura] = crearOcConFactura();

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/entregas", [
            'fecha_entrega' => '2026-02-17',
            'observaciones' => 'Entrega recibida',
        ])
        ->assertRedirect();

    $factura->refresh();
    expect($factura->estatus)->toBe('pendiente_entrega');
});

test('entrega recalcula OC a pendiente_aprobacion cuando todas tienen entrega', function () {
    [$oc, $factura] = crearOcConFactura();

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/entregas", [
            'fecha_entrega' => '2026-02-17',
        ])
        ->assertRedirect();

    $oc->refresh();
    expect($oc->estatus)->toBe('pendiente_aprobacion');
});

test('entrega no marca OC pendiente_aprobacion si hay facturas sin entrega', function () {
    [$oc, $factura1] = crearOcConFactura();
    Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $oc->proveedor_id,
        'estatus' => 'pendiente_entrega',
    ]);

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura1->id}/entregas", [
            'fecha_entrega' => '2026-02-17',
        ])
        ->assertRedirect();

    $oc->refresh();
    expect($oc->estatus)->toBe('pendiente_entrega');
});

test('entrega no crea pago automaticamente', function () {
    [$oc, $factura] = crearOcConFactura();

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/entregas", [
            'fecha_entrega' => '2026-02-17',
        ])
        ->assertRedirect();

    expect(Pago::where('pagable_type', Factura::class)->where('pagable_id', $factura->id)->count())->toBe(0);
});

test('crea registro de entrega con observaciones', function () {
    [$oc, $factura] = crearOcConFactura();

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/entregas", [
            'fecha_entrega' => '2026-02-17',
            'observaciones' => 'Todo en orden',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('costos_entregas', [
        'factura_id' => $factura->id,
        'observaciones' => 'Todo en orden',
    ]);
});
