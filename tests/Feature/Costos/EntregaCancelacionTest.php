<?php

use App\Models\Costos\Devolucion;
use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'costos.entregas.crear', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'costos.entregas.cancelar', 'guard_name' => 'web']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['costos.entregas.crear', 'costos.entregas.cancelar']);
});

function ocParaCancelacion(float $cantidad = 10): array
{
    $oc = OrdenCompra::factory()->pendienteFactura()->create(['total' => $cantidad * 100]);
    $partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'cantidad' => $cantidad,
        'precio_unitario' => 100,
        'subtotal' => $cantidad * 100,
    ]);

    return [$oc, $partida];
}

function registrarEntregaSimple($test, OrdenCompra $oc, OrdenCompraDetalle $partida, float $cantidad, array $extra = []): Entrega
{
    $test->actingAs($test->user)
        ->post("/admin/costos/ordenes-compra/{$oc->id}/entregas", array_merge([
            'fecha_entrega' => '2026-02-17',
            'tipo' => 'completa',
            'detalles' => [['orden_compra_detalle_id' => $partida->id, 'cantidad_recibida' => $cantidad]],
        ], $extra))
        ->assertRedirect();

    return Entrega::where('orden_compra_id', $oc->id)->latest('id')->firstOrFail();
}

test('cancela la entrega y regresa la OC a pendiente_entrega', function () {
    [$oc, $partida] = ocParaCancelacion();
    $entrega = registrarEntregaSimple($this, $oc, $partida, 10);

    expect($oc->fresh()->estatus->value)->toBe('pendiente_factura');

    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$entrega->id}/cancelar", ['motivo' => 'Recepción capturada por error'])
        ->assertRedirect();

    expect($entrega->fresh()->cancelada_at)->not->toBeNull();
    expect($entrega->fresh()->cancelada_por)->toBe($this->user->id);
    expect($oc->fresh()->estatus->value)->toBe('pendiente_entrega');
});

test('sin permiso costos.entregas.cancelar no puede cancelar', function () {
    [$oc, $partida] = ocParaCancelacion();
    $entrega = registrarEntregaSimple($this, $oc, $partida, 10);

    $sinPermiso = User::factory()->create();
    $sinPermiso->givePermissionTo('costos.entregas.crear');

    $this->actingAs($sinPermiso)
        ->post("/admin/costos/entregas/{$entrega->id}/cancelar", ['motivo' => 'intento sin permiso'])
        ->assertForbidden();

    expect($entrega->fresh()->cancelada_at)->toBeNull();
});

test('motivo es obligatorio', function () {
    [$oc, $partida] = ocParaCancelacion();
    $entrega = registrarEntregaSimple($this, $oc, $partida, 10);

    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$entrega->id}/cancelar", [])
        ->assertSessionHasErrors('motivo');
});

test('tras cancelar, el saldo se libera y se puede volver a recibir', function () {
    [$oc, $partida] = ocParaCancelacion(10);
    $entrega = registrarEntregaSimple($this, $oc, $partida, 10);

    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$entrega->id}/cancelar", ['motivo' => 'Error de captura'])
        ->assertRedirect();

    // El saldo volvió a 10: registrar de nuevo la cantidad completa no debe fallar.
    registrarEntregaSimple($this, $oc, $partida, 10);

    expect(Entrega::where('orden_compra_id', $oc->id)->activa()->count())->toBe(1);
});

test('no cancela si la factura ligada ya fue aprobada por costos', function () {
    [$oc, $partida] = ocParaCancelacion();
    $factura = Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'estatus' => 'pendiente_recepcion',
        'aprobada_costos' => true,
    ]);
    $entrega = registrarEntregaSimple($this, $oc, $partida, 10, ['factura_id' => $factura->id]);

    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$entrega->id}/cancelar", ['motivo' => 'intento de cancelar'])
        ->assertSessionHasErrors('error');

    expect($entrega->fresh()->cancelada_at)->toBeNull();
});

test('no cancela si hay devoluciones vigentes', function () {
    [$oc, $partida] = ocParaCancelacion();
    $entrega = registrarEntregaSimple($this, $oc, $partida, 10);
    $detalle = $entrega->detalles()->firstOrFail();

    Devolucion::factory()->create([
        'entrega_detalle_id' => $detalle->id,
        'cantidad' => 2,
        'estatus' => 'vigente',
    ]);

    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$entrega->id}/cancelar", ['motivo' => 'intento con devolucion'])
        ->assertSessionHasErrors('error');

    expect($entrega->fresh()->cancelada_at)->toBeNull();
});

test('revierte completamente_entregada de la factura al cancelar', function () {
    [$oc, $partida] = ocParaCancelacion();
    $factura = Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'estatus' => 'pendiente_recepcion',
        'completamente_entregada' => false,
    ]);
    $entrega = registrarEntregaSimple($this, $oc, $partida, 10, [
        'factura_id' => $factura->id,
        'completa_factura' => true,
    ]);

    expect($factura->fresh()->completamente_entregada)->toBeTrue();

    $this->actingAs($this->user)
        ->post("/admin/costos/entregas/{$entrega->id}/cancelar", ['motivo' => 'Error de captura'])
        ->assertRedirect();

    expect($factura->fresh()->completamente_entregada)->toBeFalse();
    expect($entrega->fresh()->cancelada_at)->not->toBeNull();
});
