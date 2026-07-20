<?php

use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionOc;
use App\Models\Costos\RequisicionSeleccion;
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

function reqConCotizacion(Departamento $depto, float $cantidad = 10, bool $manejaCredito = true): array
{
    $req = Requisicion::factory()->create(['departamento_id' => $depto->id, 'estatus' => 'borrador']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => $cantidad]);
    $proveedor = Proveedor::factory()->create(['maneja_credito' => $manejaCredito]);
    $cot = RequisicionCotizacionPrecio::create([
        'requisicion_detalle_id' => $detalle->id, 'proveedor_id' => $proveedor->id,
        'precio_unitario' => 50, 'moneda' => 'mxn',
    ]);

    return [$req, $detalle, $proveedor, $cot];
}

test('crear una selección siembra los metadatos de la OC con default según crédito', function () {
    [$req, $detalle, $proveedor, $cot] = reqConCotizacion($this->depto, 10, manejaCredito: true);

    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/selecciones', [
            'cotizacion_precio_id' => $cot->id,
            'cantidad' => 4,
            'numero_oc' => 1,
        ])
        ->assertRedirect();

    $oc = RequisicionOc::where('requisicion_id', $req->id)
        ->where('proveedor_id', $proveedor->id)
        ->where('numero_oc', 1)
        ->first();

    expect($oc)->not->toBeNull()
        ->and($oc->modo_pago->value)->toBe('credito');
});

test('proveedor sin crédito siembra la OC en contado', function () {
    [, $detalle, $proveedor, $cot] = reqConCotizacion($this->depto, 10, manejaCredito: false);

    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/selecciones', [
            'cotizacion_precio_id' => $cot->id, 'cantidad' => 4, 'numero_oc' => 1,
        ])->assertRedirect();

    expect(RequisicionOc::first()->modo_pago->value)->toBe('contado');
});

test('ocs.store actualiza modo de pago, fecha y notas', function () {
    [$req, , $proveedor] = reqConCotizacion($this->depto);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/ocs", [
            'proveedor_id' => $proveedor->id,
            'numero_oc' => 1,
            'modo_pago' => 'contado',
            'metodo_pago' => 'cheque',
            'fecha_entrega' => '2026-07-15',
            'notas' => 'Entregar en almacén central',
        ])
        ->assertRedirect();

    $oc = RequisicionOc::first();
    expect($oc->modo_pago->value)->toBe('contado')
        ->and($oc->metodo_pago)->toBe('cheque')
        ->and($oc->fecha_entrega->format('Y-m-d'))->toBe('2026-07-15')
        ->and($oc->notas)->toBe('Entregar en almacén central');

    // Segunda llamada con la misma clave actualiza (no duplica).
    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/ocs", [
            'proveedor_id' => $proveedor->id, 'numero_oc' => 1, 'modo_pago' => 'credito', 'metodo_pago' => 'transferencia',
        ])->assertRedirect();

    expect(RequisicionOc::count())->toBe(1)
        ->and(RequisicionOc::first()->modo_pago->value)->toBe('credito');
});

test('las fechas de la OC se serializan como Y-m-d para los inputs date', function () {
    [$req, , $proveedor] = reqConCotizacion($this->depto);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/ocs", [
            'proveedor_id' => $proveedor->id,
            'numero_oc' => 1,
            'modo_pago' => 'contado',
            'metodo_pago' => 'transferencia',
            'fecha_entrega' => '2026-07-15',
            'fecha_pago' => '2026-07-20',
        ])
        ->assertRedirect();

    $serializado = RequisicionOc::first()->toArray();

    // Sin hora: un <input type="date"> las muestra tal cual (no en blanco).
    expect($serializado['fecha_entrega'])->toBe('2026-07-15')
        ->and($serializado['fecha_pago'])->toBe('2026-07-20');
});

test('ocs.store guarda parcialidades en contado', function () {
    [$req, , $proveedor] = reqConCotizacion($this->depto);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/ocs", [
            'proveedor_id' => $proveedor->id, 'numero_oc' => 1, 'modo_pago' => 'contado', 'metodo_pago' => 'transferencia',
            'pagos' => [
                ['porcentaje' => 50, 'concepto' => 'Anticipo'],
                ['porcentaje' => 50, 'concepto' => 'Resto'],
            ],
        ])->assertRedirect();

    expect(RequisicionOc::first()->pagos)->toHaveCount(2);
});

test('ocs.store rechaza parcialidades que no suman 100', function () {
    [$req, , $proveedor] = reqConCotizacion($this->depto);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/ocs", [
            'proveedor_id' => $proveedor->id, 'numero_oc' => 1, 'modo_pago' => 'contado', 'metodo_pago' => 'transferencia',
            'pagos' => [
                ['porcentaje' => 50, 'concepto' => 'A'],
                ['porcentaje' => 30, 'concepto' => 'B'],
            ],
        ])->assertSessionHasErrors(['pagos']);
});

test('ocs.store ignora parcialidades cuando es crédito', function () {
    [$req, , $proveedor] = reqConCotizacion($this->depto, manejaCredito: true);

    $this->actingAs($this->compras)
        ->post("/admin/costos/requisiciones/{$req->id}/ocs", [
            'proveedor_id' => $proveedor->id, 'numero_oc' => 1, 'modo_pago' => 'credito', 'metodo_pago' => 'transferencia',
            'pagos' => [
                ['porcentaje' => 50, 'concepto' => 'A'],
                ['porcentaje' => 50, 'concepto' => 'B'],
            ],
        ])->assertRedirect();

    expect(RequisicionOc::first()->pagos)->toBeNull();
});

test('update de selección ajusta la cantidad', function () {
    [, $detalle, $proveedor, $cot] = reqConCotizacion($this->depto, 10);
    $sel = RequisicionSeleccion::create([
        'requisicion_detalle_id' => $detalle->id, 'cotizacion_precio_id' => $cot->id,
        'numero_oc' => 1, 'proveedor_id' => $proveedor->id, 'cantidad' => 4,
    ]);

    $this->actingAs($this->compras)
        ->patch("/admin/costos/requisiciones/selecciones/{$sel->id}", ['cantidad' => 7])
        ->assertRedirect();

    expect((float) $sel->refresh()->cantidad)->toBe(7.0);
});

test('update de selección a 0 la elimina', function () {
    [, $detalle, $proveedor, $cot] = reqConCotizacion($this->depto, 10);
    $sel = RequisicionSeleccion::create([
        'requisicion_detalle_id' => $detalle->id, 'cotizacion_precio_id' => $cot->id,
        'numero_oc' => 1, 'proveedor_id' => $proveedor->id, 'cantidad' => 4,
    ]);

    $this->actingAs($this->compras)
        ->patch("/admin/costos/requisiciones/selecciones/{$sel->id}", ['cantidad' => 0])
        ->assertRedirect();

    expect(RequisicionSeleccion::find($sel->id))->toBeNull();
});

test('update de selección no permite exceder la cantidad de la partida', function () {
    [, $detalle, $proveedor, $cot] = reqConCotizacion($this->depto, 10);
    RequisicionSeleccion::create([
        'requisicion_detalle_id' => $detalle->id, 'cotizacion_precio_id' => $cot->id,
        'numero_oc' => 1, 'proveedor_id' => $proveedor->id, 'cantidad' => 6,
    ]);
    $segunda = RequisicionSeleccion::create([
        'requisicion_detalle_id' => $detalle->id, 'cotizacion_precio_id' => $cot->id,
        'numero_oc' => 2, 'proveedor_id' => $proveedor->id, 'cantidad' => 3,
    ]);

    $this->actingAs($this->compras)
        ->patch("/admin/costos/requisiciones/selecciones/{$segunda->id}", ['cantidad' => 8])
        ->assertSessionHasErrors(['cantidad']);

    expect((float) $segunda->refresh()->cantidad)->toBe(3.0);
});
