<?php

use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Proveedor;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'costos.facturas.cancelar', 'guard_name' => 'web']);
    $this->user->givePermissionTo('costos.facturas.cancelar');
});

function crearFacturaCancelable(string $estatus = 'pendiente_entrega'): Factura
{
    $proveedor = Proveedor::factory()->create();
    $oc = OrdenCompra::factory()->pendienteEntrega()->create(['proveedor_id' => $proveedor->id]);

    return Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $proveedor->id,
        'estatus' => $estatus,
    ]);
}

test('cierra factura pendiente y queda cancelada', function () {
    $factura = crearFacturaCancelable('pendiente_entrega');

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/cancelar", ['motivo' => 'Cancelación motivada por test'])
        ->assertRedirect();

    expect($factura->fresh()->estatus->value)->toBe('cancelada');
});

test('no cierra factura ya pagada', function () {
    $factura = crearFacturaCancelable('pagada');

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/cancelar", ['motivo' => 'Cancelación motivada por test'])
        ->assertSessionHasErrors('estatus');

    expect($factura->fresh()->estatus->value)->toBe('pagada');
});

test('no cierra factura ya cancelada', function () {
    $factura = crearFacturaCancelable('cancelada');

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/cancelar", ['motivo' => 'Cancelación motivada por test'])
        ->assertSessionHasErrors('estatus');
});

test('no cierra factura aceptada por contabilidad', function () {
    $factura = crearFacturaCancelable('pendiente_pago');
    $factura->update([
        'aprobada_costos' => true,
        'aceptada_contabilidad' => true,
        'aceptada_contabilidad_por' => $this->user->id,
        'aceptada_contabilidad_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->post("/admin/costos/facturas/{$factura->id}/cancelar", ['motivo' => 'Cancelación motivada por test'])
        ->assertSessionHasErrors('estatus');

    expect($factura->fresh()->estatus->value)->toBe('pendiente_pago');
});

test('sin permiso no puede cerrar factura', function () {
    $otro = User::factory()->create();
    $factura = crearFacturaCancelable('pendiente_entrega');

    $this->actingAs($otro)
        ->post("/admin/costos/facturas/{$factura->id}/cancelar", ['motivo' => 'Cancelación motivada por test'])
        ->assertForbidden();

    expect($factura->fresh()->estatus->value)->toBe('pendiente_entrega');
});
