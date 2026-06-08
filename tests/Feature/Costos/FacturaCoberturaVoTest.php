<?php

use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\Factura;
use App\Models\Costos\Factura\Cobertura;
use App\Models\Costos\FacturaDetalle;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;

function facturaConPartidaYRecepcion(float $cantidadFactura, float $cantidadRecibida): array
{
    $oc = OrdenCompra::factory()->pendienteFactura()->create();
    $ocd = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'cantidad' => 100,
        'precio_unitario' => 10,
        'subtotal' => 1000,
    ]);

    $factura = Factura::factory()->create(['orden_compra_id' => $oc->id]);
    $fd = FacturaDetalle::factory()->create([
        'factura_id' => $factura->id,
        'orden_compra_detalle_id' => $ocd->id,
        'cantidad' => $cantidadFactura,
    ]);

    if ($cantidadRecibida > 0) {
        $entrega = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
        EntregaDetalle::create([
            'entrega_id' => $entrega->id,
            'orden_compra_detalle_id' => $ocd->id,
            'cantidad_recibida' => $cantidadRecibida,
        ]);
    }

    return [$factura, $fd];
}

test('cobertura() devuelve un objeto Cobertura', function () {
    [$factura] = facturaConPartidaYRecepcion(5, 5);

    expect($factura->cobertura())->toBeInstanceOf(Cobertura::class);
});

test('disponiblePara devuelve recibido menos facturado previo', function () {
    [$factura, $fd] = facturaConPartidaYRecepcion(5, 8);

    expect($factura->cobertura()->disponiblePara($fd))->toBe(8.0);
});

test('partidaCubierta true cuando la cantidad facturada cabe en lo disponible', function () {
    [$factura, $fd] = facturaConPartidaYRecepcion(5, 8);

    expect($factura->cobertura()->partidaCubierta($fd))->toBeTrue();
});

test('partidaCubierta false cuando excede lo disponible', function () {
    [$factura, $fd] = facturaConPartidaYRecepcion(5, 3);

    expect($factura->cobertura()->partidaCubierta($fd))->toBeFalse();
});

test('porPartida devuelve disponible y cubierta por FacturaDetalle id', function () {
    [$factura, $fd] = facturaConPartidaYRecepcion(5, 8);

    $snapshot = $factura->cobertura()->porPartida();

    expect($snapshot[$fd->id]['disponible'])->toBe(8.0);
    expect($snapshot[$fd->id]['cubierta'])->toBeTrue();
});

test('estaCompleta refleja la cobertura total de la factura', function () {
    [$cubierta] = facturaConPartidaYRecepcion(5, 5);
    [$incompleta] = facturaConPartidaYRecepcion(5, 2);

    expect($cubierta->cobertura()->estaCompleta())->toBeTrue();
    expect($incompleta->cobertura()->estaCompleta())->toBeFalse();
});
