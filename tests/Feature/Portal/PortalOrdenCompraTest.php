<?php

use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\Factura;
use App\Models\Costos\FacturaDetalle;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Pago;
use App\Models\Proveedor;

beforeEach(function () {
    $this->proveedor = Proveedor::factory()->create([
        'email' => 'proveedor@test.com',
        'password' => bcrypt('password'),
        'tiene_acceso_portal' => true,
        'activo' => true,
    ]);
});

function ocDePartida(int $proveedorId, float $cantidad = 10, float $precio = 100): array
{
    $oc = OrdenCompra::factory()->pendienteEntrega()->create([
        'proveedor_id' => $proveedorId,
        'total' => $cantidad * $precio,
    ]);
    $partida = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'cantidad' => $cantidad,
        'precio_unitario' => $precio,
        'subtotal' => $cantidad * $precio,
    ]);

    return [$oc->fresh(), $partida];
}

test('lista ordenes de compra del proveedor', function () {
    OrdenCompra::factory()->pendienteFactura()->count(2)->create(['proveedor_id' => $this->proveedor->id]);
    // Otra OC de otro proveedor
    OrdenCompra::factory()->pendienteFactura()->create();

    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal/ordenes-compra')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('portal/ordenes-compra/index')
            ->has('ordenes.data', 2)
        );
});

test('no muestra ordenes canceladas', function () {
    OrdenCompra::factory()->create([
        'proveedor_id' => $this->proveedor->id,
        'estatus' => 'cancelada',
    ]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal/ordenes-compra')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('ordenes.data', 0)
        );
});

test('muestra detalle de orden propia', function () {
    $oc = OrdenCompra::factory()->pendienteFactura()->create(['proveedor_id' => $this->proveedor->id]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get("/portal/ordenes-compra/{$oc->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('portal/ordenes-compra/show')
            ->where('ordenCompra.id', $oc->id)
        );
});

test('no puede ver orden de otro proveedor', function () {
    $oc = OrdenCompra::factory()->pendienteFactura()->create();

    $this->actingAs($this->proveedor, 'proveedor')
        ->get("/portal/ordenes-compra/{$oc->id}")
        ->assertForbidden();
});

test('requiere autenticacion de proveedor', function () {
    $this->get('/portal/ordenes-compra')
        ->assertRedirect('/portal/login');
});

test('etapa_proceso=recepcion cuando OC no tiene entregas', function () {
    [$oc] = ocDePartida($this->proveedor->id);

    expect($oc->etapa_proceso)->toBe('recepcion');
    expect($oc->porcentaje_recepcion)->toBe(0.0);
});

test('etapa_proceso=espera_factura cuando hay recepción sin facturar', function () {
    [$oc, $partida] = ocDePartida($this->proveedor->id);
    $entrega = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    EntregaDetalle::create([
        'entrega_id' => $entrega->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 10,
    ]);

    $oc = $oc->fresh();
    expect($oc->etapa_proceso)->toBe('espera_factura');
    expect($oc->porcentaje_recepcion)->toBe(100.0);
    expect($oc->porcentaje_facturacion)->toBe(0.0);
});

test('etapa_proceso=validacion_documentos cuando hay factura activa en pendiente_aprobacion sin saldo recibido huérfano', function () {
    [$oc, $partida] = ocDePartida($this->proveedor->id);
    $entrega = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    EntregaDetalle::create([
        'entrega_id' => $entrega->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 10,
    ]);
    $factura = Factura::factory()->pendienteAprobacion()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $this->proveedor->id,
        'total' => 1000,
    ]);
    FacturaDetalle::factory()->create([
        'factura_id' => $factura->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad' => 10,
        'precio_unitario' => 100,
        'subtotal' => 1000,
    ]);

    expect($oc->fresh()->etapa_proceso)->toBe('validacion_documentos');
});

test('etapa_proceso=completada cuando todo está pagado y recibido', function () {
    [$oc, $partida] = ocDePartida($this->proveedor->id);
    $entrega = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    EntregaDetalle::create([
        'entrega_id' => $entrega->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 10,
    ]);
    $factura = Factura::factory()->pagada()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $this->proveedor->id,
        'total' => 1000,
    ]);
    FacturaDetalle::factory()->create([
        'factura_id' => $factura->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad' => 10,
        'precio_unitario' => 100,
        'subtotal' => 1000,
    ]);
    Pago::create([
        'pagable_type' => Factura::class,
        'pagable_id' => $factura->id,
        'monto_pago' => 1000,
        'moneda' => 'mxn',
        'tipo_pago' => 'contado',
        'fecha_pago_programada' => now()->subDay(),
        'fecha_pago_realizada' => now()->subDay(),
        'estatus' => 'pagado',
    ]);

    expect($oc->fresh()->etapa_proceso)->toBe('completada');
});

test('alerta pago_vencido cuando Pago programado tiene fecha vencida', function () {
    [$oc, $partida] = ocDePartida($this->proveedor->id);
    Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    $factura = Factura::factory()->pendientePago()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $this->proveedor->id,
        'total' => 1000,
    ]);
    Pago::create([
        'pagable_type' => Factura::class,
        'pagable_id' => $factura->id,
        'monto_pago' => 1000,
        'moneda' => 'mxn',
        'tipo_pago' => 'credito',
        'fecha_pago_programada' => now()->subDays(3),
        'estatus' => 'programado',
    ]);

    expect($oc->fresh()->pago_vencido)->toBeTrue();
});

test('porcentajes se truncan a [0, 100]', function () {
    [$oc, $partida] = ocDePartida($this->proveedor->id, 10, 100);
    $entrega = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    EntregaDetalle::create([
        'entrega_id' => $entrega->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 9999,
    ]);

    expect($oc->fresh()->porcentaje_recepcion)->toBeLessThanOrEqual(100);
});

test('recepción huérfana gana sobre estatus factura activa', function () {
    // OC con entrega + factura activa + 2da entrega no facturada → espera_factura
    [$oc, $partida] = ocDePartida($this->proveedor->id, 20, 100);
    $e1 = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    EntregaDetalle::create([
        'entrega_id' => $e1->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 10,
    ]);
    $factura = Factura::factory()->pendienteAprobacion()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $this->proveedor->id,
        'total' => 1000,
    ]);
    FacturaDetalle::factory()->create([
        'factura_id' => $factura->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad' => 10,
        'precio_unitario' => 100,
        'subtotal' => 1000,
    ]);
    // 2da entrega sin facturar
    $e2 = Entrega::factory()->create(['orden_compra_id' => $oc->id]);
    EntregaDetalle::create([
        'entrega_id' => $e2->id,
        'orden_compra_detalle_id' => $partida->id,
        'cantidad_recibida' => 10,
    ]);

    expect($oc->fresh()->etapa_proceso)->toBe('espera_factura');
});
