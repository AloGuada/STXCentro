<?php

use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\OrdenCompraEstatus;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Pago;
use App\Models\Proveedor;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->proveedor = Proveedor::factory()->create([
        'email' => 'proveedor@test.com',
        'password' => bcrypt('password'),
        'tiene_acceso_portal' => true,
        'activo' => true,
    ]);
});

/** OC del proveedor con una factura por cada monto recibido. */
function ocConFacturas(Proveedor $proveedor, float $total, array $facturas = [], ?OrdenCompraEstatus $estatus = null): OrdenCompra
{
    $oc = OrdenCompra::factory()->create([
        'proveedor_id' => $proveedor->id,
        'total' => $total,
        'estatus' => ($estatus ?? OrdenCompraEstatus::PendienteFactura)->value,
    ]);

    foreach ($facturas as $datos) {
        Factura::factory()->create([
            'orden_compra_id' => $oc->id,
            'proveedor_id' => $proveedor->id,
            'total' => $datos['total'],
            'estatus' => ($datos['estatus'] ?? FacturaEstatus::PendienteRecepcion)->value,
        ]);
    }

    return $oc->refresh();
}

test('el tablero requiere sesión de proveedor', function () {
    $this->get('/portal')->assertRedirect('/portal/login');
});

test('solo lista las órdenes del proveedor autenticado', function () {
    ocConFacturas($this->proveedor, 1000);
    ocConFacturas(Proveedor::factory()->create(), 500);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/tablero')
            ->has('ordenes.data', 1)
            ->where('tab', 'activas')
        );
});

test('cada orden trae su facturado y su saldo por facturar', function () {
    ocConFacturas($this->proveedor, 1000, [
        ['total' => 400],
        ['total' => 100, 'estatus' => FacturaEstatus::Cancelada],
    ]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal')
        ->assertInertia(fn (Assert $page) => $page
            // La cancelada se ve, pero no cuenta para el facturado.
            ->has('ordenes.data.0.facturas', 2)
            ->where('ordenes.data.0.total_facturado', 400)
            ->where('ordenes.data.0.saldo_facturable', 600)
            ->where('ordenes.data.0.puede_facturar', true)
        );
});

test('la factura expone sus documentos y el contrarecibo solo con pago programado', function () {
    $oc = ocConFacturas($this->proveedor, 1000, [['total' => 1000]]);
    $factura = $oc->facturas->first();

    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal')
        ->assertInertia(fn (Assert $page) => $page
            ->where('ordenes.data.0.facturas.0.contrarecibo_url', null)
            ->where('ordenes.data.0.facturas.0.recepcion', null)
            ->where('ordenes.data.0.facturas.0.comprobantes_pago', [])
            ->where('ordenes.data.0.facturas.0.puede_subir_recepcion', true)
        );

    Pago::factory()->programado()->create([
        'pagable_type' => Factura::class,
        'pagable_id' => $factura->id,
        'fecha_pago_programada' => '2026-08-21',
    ]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal')
        ->assertInertia(fn (Assert $page) => $page
            ->where('ordenes.data.0.facturas.0.contrarecibo_url', route('portal.facturas.contrarecibo', $factura))
        );
});

test('una OC pagada y facturada al 100% vive en completadas', function () {
    ocConFacturas($this->proveedor, 1000, [
        ['total' => 1000, 'estatus' => FacturaEstatus::Pagada],
    ], OrdenCompraEstatus::Pagada);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal?tab=completadas')
        ->assertInertia(fn (Assert $page) => $page
            ->has('ordenes.data', 1)
            ->where('ordenes.data.0.completada', true)
            ->where('conteos.completadas', 1)
            ->where('conteos.activas', 0)
        );
});

test('una OC pagada pero facturada de menos sigue activa', function () {
    // Caso trampa: el estatus dice pagada, pero queda saldo por facturar.
    ocConFacturas($this->proveedor, 1000, [
        ['total' => 400, 'estatus' => FacturaEstatus::Pagada],
    ], OrdenCompraEstatus::Pagada);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal')
        ->assertInertia(fn (Assert $page) => $page
            ->has('ordenes.data', 1)
            ->where('ordenes.data.0.completada', false)
            ->where('conteos.activas', 1)
            ->where('conteos.completadas', 0)
        );
});

test('un tab inválido cae en activas', function () {
    ocConFacturas($this->proveedor, 1000);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal?tab=loquesea')
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'activas'));
});

test('el resumen suma toda la cuenta, no solo la página', function () {
    ocConFacturas($this->proveedor, 1000, [
        ['total' => 600, 'estatus' => FacturaEstatus::Pagada],
        ['total' => 400],
    ]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal')
        ->assertInertia(fn (Assert $page) => $page
            ->where('resumen.facturado', 1000)
            ->where('resumen.pagado', 600)
            ->where('resumen.pendiente', 400)
            ->where('resumen.facturas', 2)
            ->where('resumen.facturas_pagadas', 1)
        );
});

test('el número de consultas no crece con las órdenes', function () {
    foreach (range(1, 5) as $i) {
        ocConFacturas($this->proveedor, 3000, [
            ['total' => 1000],
            ['total' => 1000],
            ['total' => 1000],
        ]);
    }

    $this->actingAs($this->proveedor, 'proveedor');

    $consultas = 0;
    DB::listen(function () use (&$consultas) {
        $consultas++;
    });

    $this->get('/portal')->assertOk();

    // Los accessors de OrdenCompra consultan al serializarse; si alguien vuelve
    // a pasar modelos en vez del payload plano, esto se dispara a cientos.
    expect($consultas)->toBeLessThanOrEqual(25);
});

test('la factura trae lo que necesita su detalle: saldo, notas de crédito y parcialidades', function () {
    $oc = ocConFacturas($this->proveedor, 1000, [['total' => 1000]]);
    $factura = $oc->facturas->first();

    $padre = Pago::factory()->programado()->create([
        'pagable_type' => Factura::class,
        'pagable_id' => $factura->id,
        'monto_pago' => 1000,
        'fecha_pago_programada' => '2026-09-04',
    ]);
    Pago::factory()->hijo($padre, 1)->create(['monto_pago' => 600]);
    Pago::factory()->hijo($padre, 2)->create(['monto_pago' => 400]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get('/portal')
        ->assertInertia(fn (Assert $page) => $page
            ->where('ordenes.data.0.facturas.0.saldo_facturado', 1000)
            ->where('ordenes.data.0.facturas.0.notas_credito', [])
            ->where('ordenes.data.0.facturas.0.pago.monto', 1000)
            ->has('ordenes.data.0.facturas.0.pago.parcialidades', 2)
            ->where('ordenes.data.0.facturas.0.pago.parcialidades.0.numero', 1)
        );
});
