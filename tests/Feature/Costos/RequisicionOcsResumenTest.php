<?php

use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionOpcion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->depto = Departamento::factory()->create();
});

/**
 * Crea una partida con su cotización y la selección que la adjudica a un
 * proveedor dentro de una OC (numero_oc).
 */
function partidaAdjudicada(Requisicion $req, Proveedor $prov, float $cantidad, float $precio, int $numeroOc, string $moneda = 'mxn'): RequisicionSeleccion
{
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => $cantidad,
    ]);

    $opcion = RequisicionCotizacionOpcion::create([
        'requisicion_id' => $req->id,
        'proveedor_id' => $prov->id,
        'orden' => 1,
    ]);

    $cotizacion = RequisicionCotizacionPrecio::create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $prov->id,
        'opcion_id' => $opcion->id,
        'precio_unitario' => $precio,
        'moneda' => $moneda,
    ]);

    return RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $cotizacion->id,
        'numero_oc' => $numeroOc,
        'proveedor_id' => $prov->id,
        'cantidad' => $cantidad,
    ]);
}

test('sin selecciones el resumen de OCs viene vacío', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 3]);

    expect($req->fresh()->ocs_resumen)->toBe([]);
    expect($req->fresh()->total_neto)->toBe(0.0);
});

test('el resumen trae un renglón por proveedor adjudicado y suma el neto', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $uno = Proveedor::factory()->create(['razon_social' => 'Aceros Uno', 'nombre_comercial' => null]);
    $dos = Proveedor::factory()->create(['razon_social' => 'Tornillos Dos', 'nombre_comercial' => 'TorDos']);

    partidaAdjudicada($req, $uno, 10, 100.00, 1);
    partidaAdjudicada($req, $dos, 5, 40.00, 2);

    $resumen = $req->fresh()->ocs_resumen;

    expect($resumen)->toHaveCount(2);
    expect($resumen[0]['razon_social'])->toBe('Aceros Uno');
    expect($resumen[0]['numero_oc'])->toBe(1);
    expect($resumen[0]['folio'])->toBeNull();
    expect($resumen[1]['nombre_comercial'])->toBe('TorDos');

    // Cada OC lleva su propio neto (subtotal + IVA - retenciones) y el total
    // neto de la requisición es la suma de ambos.
    expect(round($resumen[0]['total'] + $resumen[1]['total'], 2))->toBe($req->fresh()->total_neto);
});

test('el mismo proveedor en dos OCs se reporta por separado', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $prov = Proveedor::factory()->create(['razon_social' => 'Aceros Uno', 'nombre_comercial' => null]);

    partidaAdjudicada($req, $prov, 2, 100.00, 1);
    partidaAdjudicada($req, $prov, 3, 100.00, 2);

    $resumen = $req->fresh()->ocs_resumen;

    expect($resumen)->toHaveCount(2);
    expect(array_column($resumen, 'numero_oc'))->toBe([1, 2]);
});

test('cada OC reporta su moneda y el neto total se convierte a MXN', function () {
    $req = Requisicion::factory()->create([
        'departamento_id' => $this->depto->id,
        'estatus' => 'borrador',
        'tipo_cambio' => 17.5,
    ]);
    $nacional = Proveedor::factory()->create(['razon_social' => 'Aceros Uno', 'nombre_comercial' => null]);
    $importador = Proveedor::factory()->create(['razon_social' => 'Bolts USA', 'nombre_comercial' => null]);

    partidaAdjudicada($req, $nacional, 10, 100.00, 1);
    partidaAdjudicada($req, $importador, 5, 40.00, 2, 'usd');

    $resumen = $req->fresh()->ocs_resumen;

    expect($resumen)->toHaveCount(2)
        ->and($resumen[0]['moneda'])->toBe('mxn')
        ->and($resumen[1]['moneda'])->toBe('usd');

    // El neto de la OC en dólares se convierte con el TC del documento; sumarlo
    // crudo daría un número sin unidad (pesos + dólares).
    $enPesos = round($resumen[0]['total'] + $resumen[1]['total'] * 17.5, 2);
    $sumaCruda = round($resumen[0]['total'] + $resumen[1]['total'], 2);

    expect($req->fresh()->total_neto)->toBe($enPesos)
        ->and($req->fresh()->total_neto)->not->toBe($sumaCruda);
});

test('una OC con partidas en dos monedas se reporta en renglones separados', function () {
    $req = Requisicion::factory()->create([
        'departamento_id' => $this->depto->id,
        'estatus' => 'borrador',
        'tipo_cambio' => 17.5,
    ]);
    $prov = Proveedor::factory()->create(['razon_social' => 'Aceros Uno', 'nombre_comercial' => null]);

    partidaAdjudicada($req, $prov, 2, 100.00, 1);
    partidaAdjudicada($req, $prov, 3, 50.00, 1, 'usd');

    $resumen = $req->fresh()->ocs_resumen;

    expect($resumen)->toHaveCount(2)
        ->and(array_column($resumen, 'moneda'))->toBe(['mxn', 'usd'])
        ->and(array_column($resumen, 'numero_oc'))->toBe([1, 1]);
});

test('una vez liberada, el resumen trae el folio de la OC generada', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'liberada']);
    $prov = Proveedor::factory()->create();

    $seleccion = partidaAdjudicada($req, $prov, 4, 250.00, 1);

    $oc = OrdenCompra::factory()->create([
        'requisicion_id' => $req->id,
        'proveedor_id' => $prov->id,
        'departamento_id' => $this->depto->id,
    ]);
    $ocDetalle = OrdenCompraDetalle::factory()->create([
        'orden_compra_id' => $oc->id,
        'requisicion_detalle_id' => $seleccion->requisicion_detalle_id,
    ]);
    $seleccion->update(['orden_compra_detalle_id' => $ocDetalle->id]);

    $resumen = $req->fresh()->ocs_resumen;

    expect($resumen)->toHaveCount(1);
    expect($resumen[0]['folio'])->toBe($oc->folio);
});

test('el listado de requisiciones expone el resumen de OCs', function () {
    Permission::firstOrCreate(['name' => 'costos.requisiciones.ver', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'costos.requisiciones.ver-todas', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->givePermissionTo(['costos.requisiciones.ver', 'costos.requisiciones.ver-todas']);

    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $prov = Proveedor::factory()->create(['razon_social' => 'Aceros Uno', 'nombre_comercial' => null]);
    partidaAdjudicada($req, $prov, 10, 100.00, 1);

    $this->actingAs($user)
        ->get('/admin/costos/requisiciones')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('requisiciones.data.0.ocs_resumen.0.razon_social', 'Aceros Uno')
            ->where('requisiciones.data.0.ocs_resumen.0.numero_oc', 1)
        );
});
