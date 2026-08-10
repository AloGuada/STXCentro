<?php

use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\User;

/**
 * El bloque de totales del PDF de recepción debe cuadrar: `factura->iva` sólo
 * guarda el trasladado y `factura->total` ya viene neto de retenciones, así que
 * sin desglosarlas subtotal + IVA no da el total.
 */
function entregaConFactura(array $atributosFactura = []): Entrega
{
    $oc = OrdenCompra::factory()->pendienteFactura()->create(['total' => 1000]);
    $partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'cantidad' => 10,
        'precio_unitario' => 100,
        'subtotal' => 1000,
    ]);

    $factura = Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'subtotal' => 1000,
        'iva' => 160,
        'iva_retenido' => 0,
        'isr_retenido' => 0,
        'total' => 1160,
        'moneda' => 'mxn',
        ...$atributosFactura,
    ]);

    $entrega = Entrega::factory()->create([
        'orden_compra_id' => $oc->id,
        'factura_id' => $factura->id,
    ]);

    EntregaDetalle::factory()->create([
        'entrega_id' => $entrega->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 10,
    ]);

    return $entrega;
}

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('entrega el PDF por la ruta', function () {
    $entrega = entregaConFactura(['iva_retenido' => 106.67, 'isr_retenido' => 100, 'total' => 953.33]);

    $this->actingAs($this->user)
        ->get(route('admin.costos.entregas.pdf', $entrega))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('el bloque de totales cuadra con el total de la factura', function () {
    $entrega = entregaConFactura([
        'iva_retenido' => 106.67,
        'isr_retenido' => 100,
        'total' => 953.33,
    ])->load(['ordenCompra.proveedor', 'ordenCompra.obra', 'factura', 'recibidor', 'detalles.ordenCompraDetalle']);

    $html = view('pdf.costos.formato-recepcion', ['entrega' => $entrega, 'moneda' => 'mxn'])->render();

    expect($html)->toContain('IVA TRASLADADO');
    expect($html)->toContain('IVA RETENIDO');
    expect($html)->toContain('ISR RETENIDO');
    expect($html)->toContain('-$106.67');
    expect($html)->toContain('-$100.00');
    expect($html)->toContain('$953.33');
});

it('omite los renglones de retención cuando la factura no las trae', function () {
    $entrega = entregaConFactura()
        ->load(['ordenCompra.proveedor', 'ordenCompra.obra', 'factura', 'recibidor', 'detalles.ordenCompraDetalle']);

    $html = view('pdf.costos.formato-recepcion', ['entrega' => $entrega, 'moneda' => 'mxn'])->render();

    expect($html)->toContain('IVA TRASLADADO');
    expect($html)->not->toContain('IVA RETENIDO');
    expect($html)->not->toContain('ISR RETENIDO');
    expect($html)->toContain('$1,160.00');
});
