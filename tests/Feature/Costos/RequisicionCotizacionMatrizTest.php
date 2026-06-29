<?php

use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
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

test('guardar solo precio y moneda no pisa código ni observaciones existentes', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 5]);
    $proveedor = Proveedor::factory()->create();

    $cot = RequisicionCotizacionPrecio::create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $proveedor->id,
        'precio_unitario' => 100,
        'moneda' => 'mxn',
        'codigo_producto' => 'ABC-123',
        'tiempo_entrega_dias' => 7,
        'observaciones' => 'entrega en obra',
    ]);

    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/cotizaciones', [
            'requisicion_detalle_id' => $detalle->id,
            'proveedor_id' => $proveedor->id,
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

test('quitar un proveedor borra sus cotizaciones y selecciones sin tocar a los demás', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'cotizada']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 10]);
    $provA = Proveedor::factory()->create();
    $provB = Proveedor::factory()->create();

    $cotA = RequisicionCotizacionPrecio::create([
        'requisicion_detalle_id' => $detalle->id, 'proveedor_id' => $provA->id, 'precio_unitario' => 100, 'moneda' => 'mxn',
    ]);
    $cotB = RequisicionCotizacionPrecio::create([
        'requisicion_detalle_id' => $detalle->id, 'proveedor_id' => $provB->id, 'precio_unitario' => 90, 'moneda' => 'mxn',
    ]);
    RequisicionSeleccion::create([
        'requisicion_detalle_id' => $detalle->id, 'cotizacion_precio_id' => $cotA->id,
        'numero_oc' => 1, 'proveedor_id' => $provA->id, 'cantidad' => 10,
    ]);

    $this->actingAs($this->compras)
        ->delete("/admin/costos/requisiciones/{$req->id}/proveedores/{$provA->id}")
        ->assertRedirect();

    expect(RequisicionCotizacionPrecio::find($cotA->id))->toBeNull()
        ->and(RequisicionSeleccion::where('proveedor_id', $provA->id)->count())->toBe(0)
        ->and(RequisicionCotizacionPrecio::find($cotB->id))->not->toBeNull();
});

test('no se puede quitar un proveedor de una requisición ya liberada', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'liberada']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 10]);
    $proveedor = Proveedor::factory()->create();
    RequisicionCotizacionPrecio::create([
        'requisicion_detalle_id' => $detalle->id, 'proveedor_id' => $proveedor->id, 'precio_unitario' => 100, 'moneda' => 'mxn',
    ]);

    $this->actingAs($this->compras)
        ->delete("/admin/costos/requisiciones/{$req->id}/proveedores/{$proveedor->id}")
        ->assertStatus(422);

    expect(RequisicionCotizacionPrecio::count())->toBe(1);
});
