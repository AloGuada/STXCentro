<?php

use App\Enums\Costos\OrdenCompraEstatus;
use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Services\Costos\OrdenCompraEstadoService;

beforeEach(function () {
    $this->service = app(OrdenCompraEstadoService::class);
});

test('calcular devuelve PendienteEntrega sin facturas ni entregas', function () {
    $oc = OrdenCompra::factory()->create(['estatus' => 'pendiente_aprobacion']);

    expect($this->service->calcular($oc))->toBe(OrdenCompraEstatus::PendienteEntrega);
});

test('calcular devuelve PendienteFactura con entrega y sin facturas', function () {
    $oc = OrdenCompra::factory()->create(['estatus' => 'pendiente_aprobacion']);
    Entrega::factory()->create(['orden_compra_id' => $oc->id]);

    expect($this->service->calcular($oc))->toBe(OrdenCompraEstatus::PendienteFactura);
});

test('calcular devuelve PendientePago con mix de pendiente_pago y pagada', function () {
    $oc = OrdenCompra::factory()->create();
    Factura::factory()->pendientePago()->create(['orden_compra_id' => $oc->id, 'proveedor_id' => $oc->proveedor_id]);
    Factura::factory()->pagada()->create(['orden_compra_id' => $oc->id, 'proveedor_id' => $oc->proveedor_id]);

    expect($this->service->calcular($oc))->toBe(OrdenCompraEstatus::PendientePago);
});

test('calcular devuelve Pagada cuando todas las facturas estan pagadas', function () {
    $oc = OrdenCompra::factory()->create();
    Factura::factory()->pagada()->create(['orden_compra_id' => $oc->id, 'proveedor_id' => $oc->proveedor_id]);

    expect($this->service->calcular($oc))->toBe(OrdenCompraEstatus::Pagada);
});

test('calcular devuelve null y recalcular es no-op cuando la OC esta cancelada', function () {
    $oc = OrdenCompra::factory()->create(['estatus' => 'cancelada']);

    expect($this->service->calcular($oc))->toBeNull();

    $this->service->recalcular($oc);
    $oc->refresh();

    expect($oc->estatus)->toBe(OrdenCompraEstatus::Cancelada);
});

test('recalcular persiste el nuevo estatus', function () {
    $oc = OrdenCompra::factory()->create(['estatus' => 'pendiente_aprobacion']);

    $this->service->recalcular($oc);
    $oc->refresh();

    expect($oc->estatus)->toBe(OrdenCompraEstatus::PendienteEntrega);
});
