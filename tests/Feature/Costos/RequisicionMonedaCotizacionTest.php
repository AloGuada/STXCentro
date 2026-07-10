<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
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
    foreach (['costos.requisiciones.cotizar', 'costos.requisiciones.liberar'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->compras = User::factory()->create();
    $this->compras->givePermissionTo(['costos.requisiciones.cotizar', 'costos.requisiciones.liberar']);

    $this->depto = Departamento::factory()->create();
});

test('guardar una cotización persiste la moneda', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 5]);
    $proveedor = Proveedor::factory()->create();
    $opcion = RequisicionCotizacionOpcion::create(['requisicion_id' => $req->id, 'proveedor_id' => $proveedor->id, 'orden' => 1]);

    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/cotizaciones', [
            'requisicion_detalle_id' => $detalle->id,
            'opcion_id' => $opcion->id,
            'precio_unitario' => 100,
            'moneda' => 'usd',
        ])
        ->assertRedirect();

    expect(RequisicionCotizacionPrecio::first()->moneda)->toBe('usd');
});

test('la moneda de la cotización es obligatoria y validada', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 5]);
    $proveedor = Proveedor::factory()->create();
    $opcion = RequisicionCotizacionOpcion::create(['requisicion_id' => $req->id, 'proveedor_id' => $proveedor->id, 'orden' => 1]);

    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/cotizaciones', [
            'requisicion_detalle_id' => $detalle->id,
            'opcion_id' => $opcion->id,
            'precio_unitario' => 100,
            'moneda' => 'gbp',
        ])
        ->assertSessionHasErrors(['moneda']);
});

test('al liberar, la OC toma la moneda de la cotización seleccionada', function () {
    $rubro = ObraRubro::factory()->create();
    $req = Requisicion::factory()->aprobada()->create(['departamento_id' => $this->depto->id]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'obra_rubro_id' => $rubro->id,
        'cantidad' => 4,
    ]);
    $proveedor = Proveedor::factory()->create();
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $proveedor->id,
        'precio_unitario' => 50,
        'moneda' => 'usd',
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'numero_oc' => 1,
        'proveedor_id' => $proveedor->id,
        'cantidad' => 4,
    ]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/liberar", [
            'ocs' => [
                ['proveedor_id' => $proveedor->id, 'numero_oc' => 1, 'modo_pago' => 'contado'],
            ],
        ])
        ->assertRedirect();

    expect(OrdenCompra::first()->moneda)->toBe('usd');
});

test('liberar bloquea si una OC mezcla monedas', function () {
    $rubroA = ObraRubro::factory()->create();
    $rubroB = ObraRubro::factory()->create();
    $req = Requisicion::factory()->aprobada()->create(['departamento_id' => $this->depto->id]);

    $detalleA = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'obra_rubro_id' => $rubroA->id, 'cantidad' => 2]);
    $detalleB = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'obra_rubro_id' => $rubroB->id, 'cantidad' => 2]);
    $proveedor = Proveedor::factory()->create();

    $precioA = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalleA->id, 'proveedor_id' => $proveedor->id, 'precio_unitario' => 10, 'moneda' => 'mxn',
    ]);
    $precioB = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalleB->id, 'proveedor_id' => $proveedor->id, 'precio_unitario' => 10, 'moneda' => 'usd',
    ]);
    // Mismo proveedor y mismo numero_oc → una sola OC con dos monedas.
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalleA->id, 'cotizacion_precio_id' => $precioA->id, 'numero_oc' => 1, 'proveedor_id' => $proveedor->id, 'cantidad' => 2,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalleB->id, 'cotizacion_precio_id' => $precioB->id, 'numero_oc' => 1, 'proveedor_id' => $proveedor->id, 'cantidad' => 2,
    ]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/liberar", [
            'ocs' => [
                ['proveedor_id' => $proveedor->id, 'numero_oc' => 1, 'modo_pago' => 'contado'],
            ],
        ])
        ->assertSessionHasErrors(['ocs']);

    expect(OrdenCompra::count())->toBe(0);
});
