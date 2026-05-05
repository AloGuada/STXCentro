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
        'costos.requisiciones.liberar',
        'costos.requisiciones.cotizar',
    ] as $permName) {
        Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
    }

    $this->compras = User::factory()->create();
    $this->compras->givePermissionTo(['costos.requisiciones.liberar']);

    $this->depto = Departamento::factory()->create();
});

function setupRequisicionAprobadaConRubro(Departamento $depto, ObraRubro $rubro): array
{
    $req = Requisicion::factory()->aprobada()->create([
        'departamento_id' => $depto->id,
    ]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'obra_rubro_id' => $rubro->id,
        'cantidad' => 10,
        'descripcion' => 'Tornillos',
        'unidad' => 'pza',
    ]);

    return [$req, $detalle];
}

test('liberar genera 1 OC con rubro heredado, modo_pago por OC y envio capturado', function () {
    $rubro = ObraRubro::factory()->create();
    [$req, $detalle] = setupRequisicionAprobadaConRubro($this->depto, $rubro);

    $proveedor = Proveedor::factory()->create(['maneja_credito' => true, 'dias_credito_default' => 30]);
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

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/liberar", [
            'ocs' => [
                [
                    'proveedor_id' => $proveedor->id,
                    'numero_oc' => 1,
                    'modo_pago' => 'credito',
                    'moneda' => 'mxn',
                    'envio' => 50.0,
                    'notas' => 'Notas OC 1',
                ],
            ],
        ])
        ->assertRedirect();

    expect(OrdenCompra::count())->toBe(1);
    $oc = OrdenCompra::first();
    expect($oc->requisicion_id)->toBe($req->id);
    expect($oc->proveedor_id)->toBe($proveedor->id);
    expect($oc->tipo_pago->value)->toBe('credito');
    expect((float) $oc->envio)->toBe(50.0);
    // subtotal = 25 * 10 = 250; base = 300; iva = 48; total = 348
    expect((float) $oc->total)->toBe(348.0);
    expect($oc->dias_credito)->toBe(30);

    $ocDet = $oc->detalles()->first();
    expect($ocDet->obra_rubro_id)->toBe($rubro->id);

    $req->refresh();
    expect($req->estatus->value)->toBe('liberada');
});

test('numero_oc distinto del mismo proveedor genera dos OCs separadas', function () {
    $rubro = ObraRubro::factory()->create();
    [$req, $detalle] = setupRequisicionAprobadaConRubro($this->depto, $rubro);

    $proveedor = Proveedor::factory()->create();
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $proveedor->id,
        'precio_unitario' => 100.00,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'numero_oc' => 1,
        'proveedor_id' => $proveedor->id,
        'cantidad' => 6,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'numero_oc' => 2,
        'proveedor_id' => $proveedor->id,
        'cantidad' => 4,
    ]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/liberar", [
            'ocs' => [
                ['proveedor_id' => $proveedor->id, 'numero_oc' => 1, 'modo_pago' => 'credito', 'moneda' => 'mxn'],
                ['proveedor_id' => $proveedor->id, 'numero_oc' => 2, 'modo_pago' => 'contado', 'moneda' => 'mxn'],
            ],
        ])
        ->assertRedirect();

    expect(OrdenCompra::count())->toBe(2);
    $ocs = OrdenCompra::where('proveedor_id', $proveedor->id)->orderBy('id')->get();
    expect($ocs[0]->tipo_pago->value)->toBe('credito');
    expect($ocs[1]->tipo_pago->value)->toBe('contado');
});

test('liberar genera N OCs cuando se split entre varios proveedores', function () {
    $rubro = ObraRubro::factory()->create();
    [$req, $detalle] = setupRequisicionAprobadaConRubro($this->depto, $rubro);

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
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precioA->id,
        'numero_oc' => 1,
        'proveedor_id' => $provA->id,
        'cantidad' => 6,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precioB->id,
        'numero_oc' => 1,
        'proveedor_id' => $provB->id,
        'cantidad' => 4,
    ]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/liberar", [
            'ocs' => [
                ['proveedor_id' => $provA->id, 'numero_oc' => 1, 'modo_pago' => 'credito', 'moneda' => 'mxn'],
                ['proveedor_id' => $provB->id, 'numero_oc' => 1, 'modo_pago' => 'contado', 'moneda' => 'mxn'],
            ],
        ])
        ->assertRedirect();

    expect(OrdenCompra::count())->toBe(2);
    // 6 * 20 = 120; con IVA 16% = 139.2
    expect((float) OrdenCompra::where('proveedor_id', $provA->id)->first()->total)->toBe(139.2);
    // 4 * 30 = 120; con IVA 16% = 139.2
    expect((float) OrdenCompra::where('proveedor_id', $provB->id)->first()->total)->toBe(139.2);
});

test('no puede liberar si la requisicion no esta aprobada', function () {
    $rubro = ObraRubro::factory()->create();
    $req = Requisicion::factory()->cotizada()->create(['departamento_id' => $this->depto->id]);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id,
        'obra_rubro_id' => $rubro->id,
        'cantidad' => 1,
    ]);
    $proveedor = Proveedor::factory()->create();
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $proveedor->id,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'numero_oc' => 1,
        'proveedor_id' => $proveedor->id,
        'cantidad' => 1,
    ]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/liberar", [
            'ocs' => [
                ['proveedor_id' => $proveedor->id, 'numero_oc' => 1, 'modo_pago' => 'credito', 'moneda' => 'mxn'],
            ],
        ])
        ->assertSessionHasErrors(['estatus']);
});

test('idempotencia: no libera dos veces la misma requisicion', function () {
    $rubro = ObraRubro::factory()->create();
    [$req, $detalle] = setupRequisicionAprobadaConRubro($this->depto, $rubro);

    $proveedor = Proveedor::factory()->create();
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $proveedor->id,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'numero_oc' => 1,
        'proveedor_id' => $proveedor->id,
        'cantidad' => 10,
    ]);
    OrdenCompra::factory()->create(['requisicion_id' => $req->id]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/liberar", [
            'ocs' => [
                ['proveedor_id' => $proveedor->id, 'numero_oc' => 1, 'modo_pago' => 'credito', 'moneda' => 'mxn'],
            ],
        ])
        ->assertSessionHasErrors(['estatus']);
});

test('liberar aplica impacto presupuestal con base subtotal+envio+IVA', function () {
    $rubro = ObraRubro::factory()->create(['acumulado' => 0]);
    [$req, $detalle] = setupRequisicionAprobadaConRubro($this->depto, $rubro);

    $proveedor = Proveedor::factory()->create();
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $proveedor->id,
        'precio_unitario' => 100.00,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'numero_oc' => 1,
        'proveedor_id' => $proveedor->id,
        'cantidad' => 5,
    ]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/liberar", [
            'ocs' => [
                ['proveedor_id' => $proveedor->id, 'numero_oc' => 1, 'modo_pago' => 'credito', 'moneda' => 'mxn'],
            ],
        ])
        ->assertRedirect();

    $rubro->refresh();
    expect((float) $rubro->acumulado)->toBe(500.0);
});

test('liberar requiere permiso costos.requisiciones.liberar', function () {
    $rubro = ObraRubro::factory()->create();
    [$req, $detalle] = setupRequisicionAprobadaConRubro($this->depto, $rubro);

    $proveedor = Proveedor::factory()->create();
    $precio = RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $proveedor->id,
    ]);
    RequisicionSeleccion::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'cotizacion_precio_id' => $precio->id,
        'numero_oc' => 1,
        'proveedor_id' => $proveedor->id,
        'cantidad' => 10,
    ]);

    $sinPermiso = User::factory()->create();

    $this->actingAs($sinPermiso)
        ->post("/admin/costos/requisiciones/{$req->id}/liberar", [
            'ocs' => [
                ['proveedor_id' => $proveedor->id, 'numero_oc' => 1, 'modo_pago' => 'credito', 'moneda' => 'mxn'],
            ],
        ])
        ->assertForbidden();

    expect(OrdenCompra::count())->toBe(0);
});
