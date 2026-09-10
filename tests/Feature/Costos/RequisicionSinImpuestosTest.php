<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionOpcion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionOc;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\Costos\ComparativoTotalesBuilder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['costos.requisiciones.cotizar', 'costos.requisiciones.liberar'] as $permName) {
        Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
    }

    $this->compras = User::factory()->create();
    $this->compras->givePermissionTo(['costos.requisiciones.cotizar', 'costos.requisiciones.liberar']);

    $this->depto = Departamento::factory()->create();
});

function cotizarPartida(RequisicionDetalle $detalle, Proveedor $prov, float $precio): RequisicionCotizacionPrecio
{
    $opcion = RequisicionCotizacionOpcion::create([
        'requisicion_id' => $detalle->requisicion_id,
        'proveedor_id' => $prov->id,
        'orden' => 1,
    ]);

    return RequisicionCotizacionPrecio::create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $prov->id,
        'opcion_id' => $opcion->id,
        'precio_unitario' => $precio,
        'moneda' => 'mxn',
    ]);
}

test('el toggle marca y desmarca una partida como sin impuestos', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 1]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/detalles/{$detalle->id}/sin-impuestos", ['sin_impuestos' => true])
        ->assertRedirect();
    expect($detalle->fresh()->sin_impuestos)->toBeTrue();

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/detalles/{$detalle->id}/sin-impuestos", ['sin_impuestos' => false])
        ->assertRedirect();
    expect($detalle->fresh()->sin_impuestos)->toBeFalse();
});

test('la partida sin impuestos suma al subtotal del comparativo pero no al IVA', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $prov = Proveedor::factory()->create(['tipo_persona' => 'moral']);

    foreach ([false, true] as $exenta) {
        $detalle = RequisicionDetalle::factory()->create([
            'requisicion_id' => $req->id,
            'cantidad' => 1,
            'sin_impuestos' => $exenta,
        ]);
        $precio = cotizarPartida($detalle, $prov, 1000.00);
        RequisicionSeleccion::factory()->create([
            'requisicion_detalle_id' => $detalle->id,
            'cotizacion_precio_id' => $precio->id,
            'numero_oc' => 1,
            'proveedor_id' => $prov->id,
            'cantidad' => 1,
        ]);
    }

    $totales = app(ComparativoTotalesBuilder::class)->build(
        $req->fresh()->load('detalles.selecciones.cotizacionPrecio', 'detalles.selecciones.proveedor', 'detalles.cotizaciones'),
    );

    // Subtotal 2,000 pero solo 1,000 causa IVA: 160, no 320.
    expect($totales['bloques'])->toHaveCount(1)
        ->and($totales['bloques'][0]['subtotal'])->toBe(2000.0)
        ->and($totales['bloques'][0]['iva'])->toBe(160.0)
        ->and($totales['bloques'][0]['total'])->toBe(2160.0)
        ->and($totales['bloques'][0]['neto'])->toBe(2160.0);
});

test('una partida solo cotización sin impuestos no suma IVA de referencia', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $prov = Proveedor::factory()->create(['tipo_persona' => 'moral']);

    $flete = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 1,
        'solo_cotizacion' => true,
        'sin_impuestos' => true,
    ]);
    cotizarPartida($flete, $prov, 500.00);

    $totales = app(ComparativoTotalesBuilder::class)->build(
        $req->fresh()->load('detalles.selecciones.cotizacionPrecio', 'detalles.selecciones.proveedor', 'detalles.cotizaciones'),
    );

    expect($totales['bloques'][0]['subtotal'])->toBe(500.0)
        ->and($totales['bloques'][0]['iva'])->toBe(0.0)
        ->and($totales['bloques'][0]['solo_cotizacion'])->toBe(500.0);
});

test('la marca viaja a la OC y su total excluye el IVA de esa partida', function () {
    $rubro = ObraRubro::factory()->create();
    $req = Requisicion::factory()->aprobada()->create(['departamento_id' => $this->depto->id]);
    $proveedor = Proveedor::factory()->create();

    $gravada = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'obra_rubro_id' => $rubro->id,
        'cantidad' => 10,
    ]);
    $exenta = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'obra_rubro_id' => $rubro->id,
        'cantidad' => 10,
        'sin_impuestos' => true,
    ]);

    foreach ([$gravada, $exenta] as $detalle) {
        $precio = RequisicionCotizacionPrecio::factory()->create([
            'requisicion_detalle_id' => $detalle->id,
            'proveedor_id' => $proveedor->id,
            'precio_unitario' => 25.00,
        ]);
        RequisicionSeleccion::factory()->create([
            'requisicion_detalle_id' => $detalle->id,
            'cotizacion_precio_id' => $precio->id,
            'numero_oc' => 1,
            'proveedor_id' => $proveedor->id,
            'cantidad' => 10,
        ]);
    }

    RequisicionOc::create([
        'requisicion_id' => $req->id,
        'proveedor_id' => $proveedor->id,
        'numero_oc' => 1,
        'modo_pago' => 'contado',
        'fecha_entrega' => now()->addDays(7)->format('Y-m-d'),
    ]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/liberar")
        ->assertRedirect();

    $oc = OrdenCompra::first();

    // Subtotal 500 (250 + 250); solo 250 causa IVA: 500 + 40 = 540.
    expect((float) $oc->total)->toBe(540.0);
    expect($oc->detalles->firstWhere('requisicion_detalle_id', $exenta->id)->sin_impuestos)->toBeTrue();
    expect($oc->detalles->firstWhere('requisicion_detalle_id', $gravada->id)->sin_impuestos)->toBeFalse();
});
