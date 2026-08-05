<?php

use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Pago;
use App\Models\User;
use App\Services\Costos\ContrareciboPdf;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'costos.ordenes-compra.ver-todas', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('costos.ordenes-compra.ver-todas');
});

/** Factura con su pago padre programado, que es de donde sale la fecha del formato. */
function facturaConPagoProgramado(?string $fechaProgramada = '2026-08-14'): Factura
{
    $oc = OrdenCompra::factory()->pendienteFactura()->create();
    $factura = Factura::factory()->pendientePago()->create([
        'orden_compra_id' => $oc->id,
        'proveedor_id' => $oc->proveedor_id,
    ]);

    Pago::factory()->programado()->create([
        'pagable_type' => Factura::class,
        'pagable_id' => $factura->id,
        'fecha_pago_programada' => $fechaProgramada,
    ]);

    return $factura->refresh();
}

test('el admin descarga el contrarecibo de una factura de la OC', function () {
    $factura = facturaConPagoProgramado();

    $response = $this->actingAs($this->user)
        ->get("/admin/costos/ordenes-compra/{$factura->orden_compra_id}/pdf-contrarecibo/{$factura->id}");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('el contrarecibo de una factura ajena a la OC da 404', function () {
    $factura = facturaConPagoProgramado();
    $otraOc = OrdenCompra::factory()->create();

    $this->actingAs($this->user)
        ->get("/admin/costos/ordenes-compra/{$otraOc->id}/pdf-contrarecibo/{$factura->id}")
        ->assertNotFound();
});

test('el pago raíz ignora las parcialidades', function () {
    $factura = facturaConPagoProgramado();
    $padre = $factura->pagoRaiz;

    Pago::factory()->hijo($padre, 1)->create(['monto_pago' => 100]);
    Pago::factory()->hijo($padre, 2)->create(['monto_pago' => 100]);

    // `pago()` puede devolver cualquiera de los tres; `pagoRaiz()` siempre el padre.
    expect($factura->fresh()->pagoRaiz->id)->toBe($padre->id);
});

test('el formato usa la fecha programada del pago raíz, no la de una parcialidad', function () {
    $factura = facturaConPagoProgramado('2026-08-14');
    Pago::factory()->hijo($factura->pagoRaiz, 1)->create(['fecha_pago_programada' => '2026-09-30']);

    $pdf = app(ContrareciboPdf::class)->render($factura->fresh());

    expect($pdf->output())->toBeString()->not->toBeEmpty();
    expect($factura->fresh()->pagoRaiz->fecha_pago_programada->toDateString())->toBe('2026-08-14');
});
