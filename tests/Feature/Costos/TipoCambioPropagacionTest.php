<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Departamento;
use App\Models\User;
use App\Services\Costos\SolicitudPagoDesdeOrdenCompra;

it('la solicitud de pago desde una OC en USD hereda moneda y tipo de cambio', function () {
    $depto = Departamento::factory()->create();
    $rubro = ObraRubro::factory()->create();

    $oc = OrdenCompra::factory()->pendienteEntrega()->create([
        'departamento_id' => $depto->id,
        'tipo_pago' => 'contado',
        'moneda' => 'usd',
        'tipo_cambio' => 18.5,
        'total' => 580,
    ]);
    OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'obra_rubro_id' => $rubro->id,
        'cantidad' => 5,
        'precio_unitario' => 100,
        'subtotal' => 500,
    ]);

    $sp = app(SolicitudPagoDesdeOrdenCompra::class)
        ->crear($oc->fresh(), User::factory()->create()->id, 'transferencia', null);

    expect($sp->tipo_moneda)->toBe('usd');
    expect((float) $sp->tipo_cambio)->toBe(18.5);
});
