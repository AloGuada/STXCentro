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

test('entrega parcial mantiene factura en pendiente_entrega', function () {
    [$oc, $factura] = crearOcConFactura();

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/entregas", [
            'fecha_entrega' => '2026-02-17',
            'tipo' => 'parcial',
            'observaciones' => 'Entrega parcial recibida',
        ])
        ->assertRedirect();

    $factura->refresh();
    expect($factura->estatus)->toBe('pendiente_entrega');
});

test('entrega completa cambia factura a pendiente_aprobacion', function () {
    [$oc, $factura] = crearOcConFactura();

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/entregas", [
            'fecha_entrega' => '2026-02-17',
            'tipo' => 'completa',
        ])
        ->assertRedirect();

    $factura->refresh();
    expect($factura->estatus)->toBe('pendiente_aprobacion');
});

test('entrega completa recalcula OC a pendiente_aprobacion cuando todas las facturas tienen entrega completa', function () {
    [$oc, $factura] = crearOcConFactura();

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/entregas", [
            'fecha_entrega' => '2026-02-17',
            'tipo' => 'completa',
        ])
        ->assertRedirect();

    $oc->refresh();
    expect($oc->estatus)->toBe('pendiente_aprobacion');
});

test('entrega parcial no marca OC pendiente_aprobacion', function () {
    [$oc, $factura] = crearOcConFactura();

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/entregas", [
            'fecha_entrega' => '2026-02-17',
            'tipo' => 'parcial',
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
            'tipo' => 'completa',
        ])
        ->assertRedirect();

    expect(Pago::where('pagable_type', Factura::class)->where('pagable_id', $factura->id)->count())->toBe(0);
});

test('crea registro de entrega con tipo y observaciones', function () {
    [$oc, $factura] = crearOcConFactura();

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/entregas", [
            'fecha_entrega' => '2026-02-17',
            'tipo' => 'parcial',
            'observaciones' => 'Todo en orden',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('costos_entregas', [
        'factura_id' => $factura->id,
        'tipo' => 'parcial',
        'observaciones' => 'Todo en orden',
    ]);
});

test('tipo es requerido al registrar entrega', function () {
    [$oc, $factura] = crearOcConFactura();

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/entregas", [
            'fecha_entrega' => '2026-02-17',
        ])
        ->assertSessionHasErrors('tipo');
});
