<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'costos.requisiciones.ver',
        'costos.requisiciones.crear',
        'costos.requisiciones.cotizar',
    ] as $permName) {
        Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
    }

    $this->compras = User::factory()->create();
    $this->compras->givePermissionTo(['costos.requisiciones.cotizar']);

    $this->depto = Departamento::factory()->create();
});

function setupRequisicionAprobada(Departamento $depto): array
{
    $req = Requisicion::factory()->aprobada()->create([
        'departamento_id' => $depto->id,
    ]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'cantidad' => 10,
        'descripcion' => 'Tornillos',
        'unidad' => 'pza',
    ]);

    return [$req, $detalle];
}

test('genera 1 OC cuando una partida tiene 1 proveedor', function () {
    [$req, $detalle] = setupRequisicionAprobada($this->depto);

    $proveedor = Proveedor::factory()->create();
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $proveedor->id,
        'precio_unitario' => 25.00,
    ]);
    $sel = RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'proveedor_id' => $proveedor->id,
        'cantidad' => 10,
    ]);
    $rubro = ObraRubro::factory()->create();

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/generar-ordenes", [
            'moneda' => 'mxn',
            'rubros' => [
                ['seleccion_id' => $sel->id, 'obra_rubro_id' => $rubro->id],
            ],
        ])
        ->assertRedirect();

    expect(OrdenCompra::count())->toBe(1);
    $oc = OrdenCompra::first();
    expect($oc->requisicion_id)->toBe($req->id);
    expect($oc->proveedor_id)->toBe($proveedor->id);
    expect((float) $oc->total)->toBe(250.0);
    expect($oc->detalles()->count())->toBe(1);
    $ocDet = $oc->detalles()->first();
    expect($ocDet->requisicion_detalle_id)->toBe($detalle->id);
    expect($ocDet->obra_rubro_id)->toBe($rubro->id);

    $req->refresh();
    expect($req->estatus->value)->toBe('convertida');
});

test('genera N OCs cuando la requisicion split entre varios proveedores', function () {
    [$req, $detalle] = setupRequisicionAprobada($this->depto);

    $provA = Proveedor::factory()->create();
    $provB = Proveedor::factory()->create();
    $precioA = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $provA->id,
        'precio_unitario' => 20.00,
    ]);
    $precioB = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $provB->id,
        'precio_unitario' => 30.00,
    ]);
    $selA = RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precioA->id,
        'proveedor_id' => $provA->id,
        'cantidad' => 6,
    ]);
    $selB = RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precioB->id,
        'proveedor_id' => $provB->id,
        'cantidad' => 4,
    ]);
    $rubro = ObraRubro::factory()->create();

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/generar-ordenes", [
            'moneda' => 'mxn',
            'rubros' => [
                ['seleccion_id' => $selA->id, 'obra_rubro_id' => $rubro->id],
                ['seleccion_id' => $selB->id, 'obra_rubro_id' => $rubro->id],
            ],
        ])
        ->assertRedirect();

    expect(OrdenCompra::count())->toBe(2);
    expect(OrdenCompra::where('proveedor_id', $provA->id)->first()->total)->toBe('120.00');
    expect(OrdenCompra::where('proveedor_id', $provB->id)->first()->total)->toBe('120.00');
});

test('no puede generar OCs si la requisicion no esta aprobada', function () {
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id]);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 1]);
    $proveedor = Proveedor::factory()->create();
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $proveedor->id,
    ]);
    $sel = RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'proveedor_id' => $proveedor->id,
        'cantidad' => 1,
    ]);
    $rubro = ObraRubro::factory()->create();

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/generar-ordenes", [
            'moneda' => 'mxn',
            'rubros' => [['seleccion_id' => $sel->id, 'obra_rubro_id' => $rubro->id]],
        ])
        ->assertSessionHasErrors(['estatus']);
});

test('idempotencia: no genera OCs dos veces para la misma requisicion', function () {
    [$req, $detalle] = setupRequisicionAprobada($this->depto);

    $proveedor = Proveedor::factory()->create();
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $proveedor->id,
    ]);
    $sel = RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'proveedor_id' => $proveedor->id,
        'cantidad' => 10,
    ]);
    OrdenCompra::factory()->create(['requisicion_id' => $req->id]);
    $rubro = ObraRubro::factory()->create();

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/generar-ordenes", [
            'moneda' => 'mxn',
            'rubros' => [
                ['seleccion_id' => $sel->id, 'obra_rubro_id' => $rubro->id],
            ],
        ])
        ->assertSessionHasErrors(['estatus']);
});

test('aplica impacto presupuestal a obra_rubros', function () {
    [$req, $detalle] = setupRequisicionAprobada($this->depto);

    $proveedor = Proveedor::factory()->create();
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $proveedor->id,
        'precio_unitario' => 100.00,
    ]);
    $sel = RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'proveedor_id' => $proveedor->id,
        'cantidad' => 5,
    ]);
    $rubro = ObraRubro::factory()->create(['acumulado' => 0]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/generar-ordenes", [
            'moneda' => 'mxn',
            'rubros' => [
                ['seleccion_id' => $sel->id, 'obra_rubro_id' => $rubro->id],
            ],
        ])
        ->assertRedirect();

    $rubro->refresh();
    expect((float) $rubro->acumulado)->toBe(500.0);
});
