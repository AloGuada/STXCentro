<?php

use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
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

/** Factura del proveedor, con o sin pago programado. */
function facturaDePortal(Proveedor $proveedor, ?string $fechaProgramada): Factura
{
    $oc = OrdenCompra::factory()->create(['proveedor_id' => $proveedor->id]);
    $factura = Factura::factory()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $proveedor->id,
    ]);

    if ($fechaProgramada !== null) {
        Pago::factory()->programado()->create([
            'pagable_type' => Factura::class,
            'pagable_id' => $factura->id,
            'fecha_pago_programada' => $fechaProgramada,
        ]);
    }

    return $factura->refresh();
}

test('descarga el contrarecibo cuando ya hay pago programado', function () {
    $factura = facturaDePortal($this->proveedor, '2026-08-14');

    $response = $this->actingAs($this->proveedor, 'proveedor')
        ->get("/portal/facturas/{$factura->id}/contrarecibo");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('sin pago programado el contrarecibo no existe todavía', function () {
    $factura = facturaDePortal($this->proveedor, null);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get("/portal/facturas/{$factura->id}/contrarecibo")
        ->assertNotFound();
});

test('un pago sin fecha programada tampoco lo habilita', function () {
    $factura = facturaDePortal($this->proveedor, null);
    Pago::factory()->create([
        'pagable_type' => Factura::class,
        'pagable_id' => $factura->id,
        'fecha_pago_programada' => null,
    ]);

    $this->actingAs($this->proveedor, 'proveedor')
        ->get("/portal/facturas/{$factura->id}/contrarecibo")
        ->assertNotFound();
});

test('no puede descargar el contrarecibo de otro proveedor', function () {
    $ajena = facturaDePortal(Proveedor::factory()->create(), '2026-08-14');

    $this->actingAs($this->proveedor, 'proveedor')
        ->get("/portal/facturas/{$ajena->id}/contrarecibo")
        ->assertForbidden();
});

test('sin sesión de proveedor manda al login', function () {
    $factura = facturaDePortal($this->proveedor, '2026-08-14');

    $this->get("/portal/facturas/{$factura->id}/contrarecibo")->assertRedirect('/portal/login');
});
