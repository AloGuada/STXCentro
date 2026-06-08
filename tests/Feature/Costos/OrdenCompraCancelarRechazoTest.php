<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\SolicitudPago;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function ocContadoConPresupuesto(string $estatus = 'pendiente_entrega'): array
{
    $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 5000]);
    $oc = OrdenCompra::factory()->create([
        'tipo_pago' => 'contado',
        'estatus' => $estatus,
        'total' => 5000,
    ]);
    OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'obra_rubro_id' => $obraRubro->id,
        'cantidad' => 1,
        'precio_unitario' => 5000,
        'subtotal' => 5000,
    ]);

    return [$oc, $obraRubro];
}

test('cancelarPorRechazoDePago cancela la OC y revierte el presupuesto', function () {
    [$oc, $obraRubro] = ocContadoConPresupuesto();

    expect($oc->cancelarPorRechazoDePago('SP rechazada', $this->user->id))->toBeTrue();

    $oc->refresh();
    expect($oc->estatus->value)->toBe('cancelada');
    expect((float) $obraRubro->fresh()->acumulado)->toBe(0.0);
    expect($oc->cancelacion)->not->toBeNull();
});

test('cancelarPorRechazoDePago es no-op si la OC ya avanzo', function () {
    [$oc, $obraRubro] = ocContadoConPresupuesto('pendiente_pago');

    expect($oc->cancelarPorRechazoDePago('SP rechazada', $this->user->id))->toBeFalse();

    $oc->refresh();
    expect($oc->estatus->value)->toBe('pendiente_pago');
    expect((float) $obraRubro->fresh()->acumulado)->toBe(5000.0);
});

test('rechazar la solicitud de pago de contado cancela la OC y revierte presupuesto', function () {
    [$oc, $obraRubro] = ocContadoConPresupuesto();
    $sp = SolicitudPago::factory()->create([
        'orden_compra_id' => $oc->id,
        'estatus' => 'pendiente_firma',
    ]);

    $sp->onAprobacionRechazada('No autorizado por gerencia', $this->user->id);

    expect($sp->fresh()->estatus->value)->toBe('cancelada');
    expect($oc->fresh()->estatus->value)->toBe('cancelada');
    expect((float) $obraRubro->fresh()->acumulado)->toBe(0.0);
});
