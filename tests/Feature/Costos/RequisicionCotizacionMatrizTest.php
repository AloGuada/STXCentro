<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionOpcion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Costos\UsoCfdi;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'costos.requisiciones.cotizar', 'guard_name' => 'web']);

    $this->compras = User::factory()->create();
    $this->compras->givePermissionTo('costos.requisiciones.cotizar');

    $this->depto = Departamento::factory()->create();
});

function opcionDe(Requisicion $req, Proveedor $prov, int $orden = 1, ?string $etiqueta = null): RequisicionCotizacionOpcion
{
    return RequisicionCotizacionOpcion::create([
        'requisicion_id' => $req->id,
        'proveedor_id' => $prov->id,
        'orden' => $orden,
        'etiqueta' => $etiqueta,
    ]);
}

test('guardar precio de una celda no pisa código ni observaciones existentes', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 5]);
    $proveedor = Proveedor::factory()->create();
    $opcion = opcionDe($req, $proveedor);

    $cot = RequisicionCotizacionPrecio::create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $proveedor->id,
        'opcion_id' => $opcion->id,
        'precio_unitario' => 100,
        'moneda' => 'mxn',
        'codigo_producto' => 'ABC-123',
        'tiempo_entrega_dias' => 7,
        'observaciones' => 'entrega en obra',
    ]);

    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/cotizaciones', [
            'requisicion_detalle_id' => $detalle->id,
            'opcion_id' => $opcion->id,
            'precio_unitario' => 150,
            'moneda' => 'usd',
        ])
        ->assertRedirect();

    $cot->refresh();
    expect($cot->precio_unitario)->toBe('150.00')
        ->and($cot->moneda)->toBe('usd')
        ->and($cot->codigo_producto)->toBe('ABC-123')
        ->and($cot->tiempo_entrega_dias)->toBe(7)
        ->and($cot->observaciones)->toBe('entrega en obra');
});

test('un proveedor puede tener dos opciones con precio y descripción distintos', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 5]);
    $prov = Proveedor::factory()->create();
    $op1 = opcionDe($req, $prov, 1, 'Makita');
    $op2 = opcionDe($req, $prov, 2, 'Black&Decker');

    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/cotizaciones', [
            'requisicion_detalle_id' => $detalle->id, 'opcion_id' => $op1->id,
            'precio_unitario' => 100, 'descripcion' => 'Taladro Makita', 'moneda' => 'mxn',
        ])->assertRedirect();

    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/cotizaciones', [
            'requisicion_detalle_id' => $detalle->id, 'opcion_id' => $op2->id,
            'precio_unitario' => 80, 'descripcion' => 'Taladro B&D', 'moneda' => 'mxn',
        ])->assertRedirect();

    $cots = RequisicionCotizacionPrecio::where('requisicion_detalle_id', $detalle->id)->get();
    expect($cots)->toHaveCount(2)
        ->and($cots->pluck('proveedor_id')->unique()->all())->toBe([$prov->id])
        ->and($cots->firstWhere('opcion_id', $op1->id)->descripcion)->toBe('Taladro Makita')
        ->and($cots->firstWhere('opcion_id', $op2->id)->descripcion)->toBe('Taladro B&D');
});

test('los días de envío se aplican a todas las celdas de una opción', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $d1 = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 5]);
    $d2 = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 3]);
    $prov = Proveedor::factory()->create();
    $otro = Proveedor::factory()->create();
    $opProv = opcionDe($req, $prov);
    $opOtro = opcionDe($req, $otro);

    $cot1 = RequisicionCotizacionPrecio::create(['requisicion_detalle_id' => $d1->id, 'proveedor_id' => $prov->id, 'opcion_id' => $opProv->id, 'precio_unitario' => 100, 'moneda' => 'mxn']);
    $cot2 = RequisicionCotizacionPrecio::create(['requisicion_detalle_id' => $d2->id, 'proveedor_id' => $prov->id, 'opcion_id' => $opProv->id, 'precio_unitario' => 50, 'moneda' => 'mxn']);
    $cotOtro = RequisicionCotizacionPrecio::create(['requisicion_detalle_id' => $d1->id, 'proveedor_id' => $otro->id, 'opcion_id' => $opOtro->id, 'precio_unitario' => 110, 'moneda' => 'mxn']);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/cotizaciones/tiempo-entrega", [
            'opcion_id' => $opProv->id,
            'tiempo_entrega_dias' => 10,
        ])
        ->assertRedirect();

    expect($cot1->fresh()->tiempo_entrega_dias)->toBe(10)
        ->and($cot2->fresh()->tiempo_entrega_dias)->toBe(10)
        ->and($cotOtro->fresh()->tiempo_entrega_dias)->toBeNull();
});

test('agregar opción crea la columna con el siguiente orden del proveedor', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $prov = Proveedor::factory()->create();

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/opciones", ['proveedor_id' => $prov->id])
        ->assertRedirect();
    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/opciones", ['proveedor_id' => $prov->id, 'etiqueta' => 'Opción B'])
        ->assertRedirect();

    $opciones = RequisicionCotizacionOpcion::where('requisicion_id', $req->id)->orderBy('orden')->get();
    expect($opciones)->toHaveCount(2)
        ->and($opciones[0]->orden)->toBe(1)
        ->and($opciones[1]->orden)->toBe(2)
        ->and($opciones[1]->etiqueta)->toBe('Opción B');
});

test('agregar proveedor por nombre crea un proveedor pendiente y su opción', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/opciones", ['proveedor_nuevo' => '  Ferretería El Tornillo  '])
        ->assertRedirect();

    $proveedor = Proveedor::where('razon_social', 'Ferretería El Tornillo')->first();
    expect($proveedor)->not->toBeNull()
        ->and($proveedor->estatus)->toBe(App\Enums\ProveedorEstatus::PendienteValidacion)
        ->and($proveedor->activo)->toBeFalse()
        ->and($proveedor->tipo_proveedor)->toBeNull()
        ->and($proveedor->creado_por)->toBe($this->compras->id);

    $opcion = RequisicionCotizacionOpcion::where('requisicion_id', $req->id)->first();
    expect($opcion)->not->toBeNull()
        ->and($opcion->proveedor_id)->toBe($proveedor->id)
        ->and($opcion->orden)->toBe(1);
});

test('agregar opción exige proveedor por id o por nombre', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/opciones", [])
        ->assertSessionHasErrors(['proveedor_id', 'proveedor_nuevo']);
});

test('borrar una opción borra sus celdas y selecciones en cascada', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 10]);
    $prov = Proveedor::factory()->create();
    $op1 = opcionDe($req, $prov, 1);
    $op2 = opcionDe($req, $prov, 2);

    $cot1 = RequisicionCotizacionPrecio::create(['requisicion_detalle_id' => $detalle->id, 'proveedor_id' => $prov->id, 'opcion_id' => $op1->id, 'precio_unitario' => 100, 'moneda' => 'mxn']);
    $cot2 = RequisicionCotizacionPrecio::create(['requisicion_detalle_id' => $detalle->id, 'proveedor_id' => $prov->id, 'opcion_id' => $op2->id, 'precio_unitario' => 90, 'moneda' => 'mxn']);
    RequisicionSeleccion::create(['requisicion_detalle_id' => $detalle->id, 'cotizacion_precio_id' => $cot1->id, 'numero_oc' => 1, 'proveedor_id' => $prov->id, 'cantidad' => 10]);

    $this->actingAs($this->compras)
        ->delete("/admin/costos/requisiciones/opciones/{$op1->id}")
        ->assertRedirect();

    expect(RequisicionCotizacionOpcion::find($op1->id))->toBeNull()
        ->and(RequisicionCotizacionPrecio::find($cot1->id))->toBeNull()
        ->and(RequisicionSeleccion::where('cotizacion_precio_id', $cot1->id)->count())->toBe(0)
        ->and(RequisicionCotizacionOpcion::find($op2->id))->not->toBeNull()
        ->and(RequisicionCotizacionPrecio::find($cot2->id))->not->toBeNull();
});

test('quitar un proveedor borra sus opciones, cotizaciones y selecciones sin tocar a los demás', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 10]);
    $provA = Proveedor::factory()->create();
    $provB = Proveedor::factory()->create();
    $opA = opcionDe($req, $provA);
    $opB = opcionDe($req, $provB);

    $cotA = RequisicionCotizacionPrecio::create(['requisicion_detalle_id' => $detalle->id, 'proveedor_id' => $provA->id, 'opcion_id' => $opA->id, 'precio_unitario' => 100, 'moneda' => 'mxn']);
    $cotB = RequisicionCotizacionPrecio::create(['requisicion_detalle_id' => $detalle->id, 'proveedor_id' => $provB->id, 'opcion_id' => $opB->id, 'precio_unitario' => 90, 'moneda' => 'mxn']);
    RequisicionSeleccion::create(['requisicion_detalle_id' => $detalle->id, 'cotizacion_precio_id' => $cotA->id, 'numero_oc' => 1, 'proveedor_id' => $provA->id, 'cantidad' => 10]);

    $this->actingAs($this->compras)
        ->delete("/admin/costos/requisiciones/{$req->id}/proveedores/{$provA->id}")
        ->assertRedirect();

    expect(RequisicionCotizacionPrecio::find($cotA->id))->toBeNull()
        ->and(RequisicionCotizacionOpcion::find($opA->id))->toBeNull()
        ->and(RequisicionSeleccion::where('proveedor_id', $provA->id)->count())->toBe(0)
        ->and(RequisicionCotizacionPrecio::find($cotB->id))->not->toBeNull()
        ->and(RequisicionCotizacionOpcion::find($opB->id))->not->toBeNull();
});

test('agregar una partida desde cotización crea el renglón', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador', 'presupuesto_id' => null]);
    $rubro = ObraRubro::factory()->create();
    $uso = UsoCfdi::factory()->create(['activo' => true]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/detalles", [
            'descripcion' => 'Cemento gris 50kg',
            'unidad' => 'saco',
            'cantidad' => 20,
            'obra_rubro_id' => $rubro->id,
            'uso_cfdi_id' => $uso->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $detalle = RequisicionDetalle::where('requisicion_id', $req->id)->first();
    expect($detalle)->not->toBeNull()
        ->and($detalle->descripcion)->toBe('Cemento gris 50kg')
        ->and($detalle->unidad)->toBe('saco')
        ->and($detalle->cantidad)->toBe('20.00')
        ->and($detalle->obra_rubro_id)->toBe($rubro->id)
        ->and($detalle->uso_cfdi_id)->toBe($uso->id);
});

test('agregar partida exige centro de costos y uso de CFDI', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador', 'presupuesto_id' => null]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/detalles", [
            'descripcion' => 'Sin centro', 'unidad' => 'pza', 'cantidad' => 1,
        ])
        ->assertSessionHasErrors(['obra_rubro_id', 'uso_cfdi_id']);

    expect(RequisicionDetalle::count())->toBe(0);
});

test('no se puede agregar partida a una requisición ya liberada', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'liberada', 'presupuesto_id' => null]);
    $rubro = ObraRubro::factory()->create();
    $uso = UsoCfdi::factory()->create(['activo' => true]);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/detalles", [
            'descripcion' => 'Tarde', 'unidad' => 'pza', 'cantidad' => 1,
            'obra_rubro_id' => $rubro->id, 'uso_cfdi_id' => $uso->id,
        ])
        ->assertStatus(422);

    expect(RequisicionDetalle::count())->toBe(0);
});

test('quitar una partida borra sus cotizaciones y selecciones pero no las opciones', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 5]);
    $otro = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 3]);
    $prov = Proveedor::factory()->create();
    $op = opcionDe($req, $prov);

    $cot = RequisicionCotizacionPrecio::create(['requisicion_detalle_id' => $detalle->id, 'proveedor_id' => $prov->id, 'opcion_id' => $op->id, 'precio_unitario' => 100, 'moneda' => 'mxn']);
    RequisicionSeleccion::create(['requisicion_detalle_id' => $detalle->id, 'cotizacion_precio_id' => $cot->id, 'numero_oc' => 1, 'proveedor_id' => $prov->id, 'cantidad' => 5]);

    $this->actingAs($this->compras)
        ->delete("/admin/costos/requisiciones/detalles/{$detalle->id}")
        ->assertRedirect();

    expect(RequisicionDetalle::find($detalle->id))->toBeNull()
        ->and(RequisicionCotizacionPrecio::find($cot->id))->toBeNull()
        ->and(RequisicionSeleccion::where('cotizacion_precio_id', $cot->id)->count())->toBe(0)
        ->and(RequisicionCotizacionOpcion::find($op->id))->not->toBeNull()
        ->and(RequisicionDetalle::find($otro->id))->not->toBeNull();
});

test('no se puede quitar un proveedor de una requisición ya liberada', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'liberada']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 10]);
    $proveedor = Proveedor::factory()->create();
    $opcion = opcionDe($req, $proveedor);
    RequisicionCotizacionPrecio::create([
        'requisicion_detalle_id' => $detalle->id, 'proveedor_id' => $proveedor->id, 'opcion_id' => $opcion->id, 'precio_unitario' => 100, 'moneda' => 'mxn',
    ]);

    $this->actingAs($this->compras)
        ->delete("/admin/costos/requisiciones/{$req->id}/proveedores/{$proveedor->id}")
        ->assertStatus(422);

    expect(RequisicionCotizacionPrecio::count())->toBe(1);
});
