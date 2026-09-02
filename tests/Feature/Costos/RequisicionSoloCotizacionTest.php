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
use App\Services\Costos\BuscadorMejorProveedor;
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

function precioDe(RequisicionDetalle $detalle, Proveedor $prov, float $precio): RequisicionCotizacionPrecio
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

test('el toggle marca y desmarca una partida como solo cotización', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 1]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/detalles/{$detalle->id}/solo-cotizacion", ['solo_cotizacion' => true])
        ->assertRedirect();
    expect($detalle->fresh()->solo_cotizacion)->toBeTrue();

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/detalles/{$detalle->id}/solo-cotizacion", ['solo_cotizacion' => false])
        ->assertRedirect();
    expect($detalle->fresh()->solo_cotizacion)->toBeFalse();
});

test('una partida solo cotización no afecta el total neto a pagar', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $prov = Proveedor::factory()->create();

    $normal = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 10]);
    $precioNormal = precioDe($normal, $prov, 25.00);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $normal->id,
        'cotizacion_precio_id' => $precioNormal->id,
        'numero_oc' => 1,
        'proveedor_id' => $prov->id,
        'cantidad' => 10,
    ]);

    $netoSinFlete = $req->fresh()->total_neto;
    expect($netoSinFlete)->toBeGreaterThan(0.0);

    // Un flete "solo cotización" con selección propia NO debe alterar el neto.
    $flete = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 1,
        'solo_cotizacion' => true,
    ]);
    $precioFlete = precioDe($flete, $prov, 500.00);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $flete->id,
        'cotizacion_precio_id' => $precioFlete->id,
        'numero_oc' => 1,
        'proveedor_id' => $prov->id,
        'cantidad' => 1,
    ]);

    expect($req->fresh()->total_neto)->toBe($netoSinFlete);
});

test('el comparativo suma la partida solo cotización al total y al neto a pagar', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $prov = Proveedor::factory()->create();

    $normal = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 4]);
    $precioNormal = precioDe($normal, $prov, 100.00);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $normal->id,
        'cotizacion_precio_id' => $precioNormal->id,
        'numero_oc' => 1,
        'proveedor_id' => $prov->id,
        'cantidad' => 4,
    ]);

    $flete = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 1,
        'solo_cotizacion' => true,
    ]);
    $precioFlete = precioDe($flete, $prov, 999.00);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $flete->id,
        'cotizacion_precio_id' => $precioFlete->id,
        'numero_oc' => 1,
        'proveedor_id' => $prov->id,
        'cantidad' => 1,
    ]);

    $totales = app(ComparativoTotalesBuilder::class)->build(
        $req->fresh()->load('detalles.selecciones.cotizacionPrecio', 'detalles.selecciones.proveedor', 'detalles.cotizaciones'),
    );

    // El flete de referencia (999) suma al subtotal del comparativo:
    // 4 × 100 + 999 = 1,399, y también al neto a pagar: 1,399 × 1.16 =
    // 1,622.84. `solo_cotizacion` queda solo como nota de cuánto de ese neto
    // viene del flete (999 × 1.16 = 1,158.84).
    expect($totales['bloques'])->toHaveCount(1)
        ->and($totales['bloques'][0]['subtotal'])->toBe(1399.0)
        ->and($totales['bloques'][0]['solo_cotizacion'])->toBe(round(999 * 1.16, 2))
        ->and($totales['bloques'][0]['neto'])->toBe(round(1399 * 1.16, 2));
});

test('el mejor proveedor ignora las partidas solo cotización', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $barato = Proveedor::factory()->create();
    $caro = Proveedor::factory()->create();

    $partida = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 2]);
    precioDe($partida, $barato, 50.00);
    precioDe($partida, $caro, 80.00);

    // Un flete "solo cotización" que SOLO cotizó el proveedor caro no debe
    // descalificar al barato (que no cotizó el flete) como mejor proveedor.
    $flete = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 1,
        'solo_cotizacion' => true,
    ]);
    precioDe($flete, $caro, 300.00);

    $mejor = app(BuscadorMejorProveedor::class)->buscar($req->fresh());

    expect($mejor['id'])->toBe($barato->id)
        ->and($mejor['total'])->toBe(100.0); // 50 × 2, sin el flete.
});

test('liberar no genera línea de OC para una partida solo cotización', function () {
    $rubro = ObraRubro::factory()->create();
    $req = Requisicion::factory()->aprobada()->create(['departamento_id' => $this->depto->id]);
    $prov = Proveedor::factory()->create();

    $normal = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'obra_rubro_id' => $rubro->id,
        'cantidad' => 5,
    ]);
    $precioNormal = precioDe($normal, $prov, 20.00);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $normal->id,
        'cotizacion_precio_id' => $precioNormal->id,
        'numero_oc' => 1,
        'proveedor_id' => $prov->id,
        'cantidad' => 5,
    ]);

    // Flete solo cotización: sin rubro, sin uso CFDI, sin selección. No debe
    // bloquear la liberación ni aparecer en la OC.
    RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'obra_rubro_id' => null,
        'uso_cfdi_id' => null,
        'cantidad' => 1,
        'solo_cotizacion' => true,
    ]);

    RequisicionOc::create([
        'requisicion_id' => $req->id,
        'proveedor_id' => $prov->id,
        'numero_oc' => 1,
        'modo_pago' => 'credito',
        'fecha_entrega' => now()->addDays(7)->format('Y-m-d'),
    ]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/liberar")
        ->assertRedirect();

    expect(OrdenCompra::count())->toBe(1);
    $oc = OrdenCompra::first();
    expect($oc->detalles()->count())->toBe(1)
        ->and((float) $oc->detalles()->first()->subtotal)->toBe(100.0); // 20 × 5.
});

test('no se puede crear una selección sobre una partida solo cotización', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $prov = Proveedor::factory()->create();

    $flete = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 1,
        'solo_cotizacion' => true,
    ]);
    $precio = precioDe($flete, $prov, 500.00);

    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/selecciones', [
            'cotizacion_precio_id' => $precio->id,
            'cantidad' => 1,
        ])
        ->assertSessionHasErrors('cotizacion_precio_id');

    expect(RequisicionSeleccion::count())->toBe(0);
});

test('marcar solo cotización elimina las selecciones existentes de la partida', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $prov = Proveedor::factory()->create();

    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 3]);
    $precio = precioDe($detalle, $prov, 10.00);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'numero_oc' => 1,
        'proveedor_id' => $prov->id,
        'cantidad' => 3,
    ]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/detalles/{$detalle->id}/solo-cotizacion", ['solo_cotizacion' => true])
        ->assertRedirect();

    expect($detalle->fresh()->solo_cotizacion)->toBeTrue()
        ->and($detalle->selecciones()->count())->toBe(0);
});

test('la pantalla recibe el mejor proveedor, para calcular la referencia igual que el PDF', function () {
    foreach (['costos.requisiciones.ver', 'costos.requisiciones.ver-todas'] as $permName) {
        Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
    }
    $this->compras->givePermissionTo(['costos.requisiciones.ver', 'costos.requisiciones.ver-todas']);

    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $barato = Proveedor::factory()->create();
    $caro = Proveedor::factory()->create();

    $partida = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 2]);
    precioDe($partida, $barato, 50.00);
    precioDe($partida, $caro, 80.00);

    $this->actingAs($this->compras)
        ->get("/admin/costos/requisiciones/{$req->id}")
        ->assertInertia(fn ($page) => $page->where('requisicion.mejor_proveedor.id', $barato->id));
});

test('el PDF del comparativo se genera con partidas solo cotización presentes', function () {
    Permission::firstOrCreate(['name' => 'costos.requisiciones.ver', 'guard_name' => 'web']);
    $this->compras->givePermissionTo('costos.requisiciones.ver');

    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'pendiente_aprobacion']);
    $prov = Proveedor::factory()->create();

    $normal = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 2]);
    precioDe($normal, $prov, 100.00);

    RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 1,
        'solo_cotizacion' => true,
        'descripcion' => 'Flete de referencia',
    ]);

    $this->actingAs($this->compras)
        ->get("/admin/costos/requisiciones/{$req->id}/pdf")
        ->assertOk();
});
