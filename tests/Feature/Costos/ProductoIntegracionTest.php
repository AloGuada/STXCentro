<?php

use App\Models\Costos\Producto;
use App\Models\Costos\ProductoPrecio;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionDetalle;
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

test('cotizar un precio lo registra en el histórico del producto', function () {
    $producto = Producto::factory()->create();
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'producto_id' => $producto->id, 'cantidad' => 5]);
    $proveedor = Proveedor::factory()->create();

    $this->actingAs($this->compras)
        ->post('/admin/costos/requisiciones/cotizaciones', [
            'requisicion_detalle_id' => $detalle->id, 'proveedor_id' => $proveedor->id, 'precio_unitario' => 123.45, 'moneda' => 'mxn',
        ])
        ->assertRedirect();

    $precio = ProductoPrecio::first();
    expect($precio)->not->toBeNull()
        ->and($precio->producto_id)->toBe($producto->id)
        ->and($precio->proveedor_id)->toBe($proveedor->id)
        ->and((float) $precio->precio)->toBe(123.45)
        ->and($precio->requisicion_id)->toBe($req->id);
});

test('re-cotizar el mismo proveedor en la misma requisición no duplica el histórico', function () {
    $producto = Producto::factory()->create();
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'producto_id' => $producto->id, 'cantidad' => 5]);
    $proveedor = Proveedor::factory()->create();

    foreach ([100, 150] as $p) {
        $this->actingAs($this->compras)->post('/admin/costos/requisiciones/cotizaciones', [
            'requisicion_detalle_id' => $detalle->id, 'proveedor_id' => $proveedor->id, 'precio_unitario' => $p, 'moneda' => 'mxn',
        ])->assertRedirect();
    }

    expect(ProductoPrecio::count())->toBe(1)
        ->and((float) ProductoPrecio::first()->precio)->toBe(150.0);
});

test('una partida sin producto del catálogo no genera histórico', function () {
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'borrador']);
    $detalle = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'producto_id' => null, 'cantidad' => 5]);
    $proveedor = Proveedor::factory()->create();

    $this->actingAs($this->compras)->post('/admin/costos/requisiciones/cotizaciones', [
        'requisicion_detalle_id' => $detalle->id, 'proveedor_id' => $proveedor->id, 'precio_unitario' => 50, 'moneda' => 'mxn',
    ])->assertRedirect();

    expect(ProductoPrecio::count())->toBe(0);
});

test('compras edita el producto desde cotización y sincroniza la partida', function () {
    $producto = Producto::factory()->create(['descripcion' => 'Viejo', 'codigo' => 'OLD-1']);
    $req = Requisicion::factory()->create(['departamento_id' => $this->depto->id, 'estatus' => 'cotizada']);
    $detalle = RequisicionDetalle::factory()->create([
        'requisicion_id' => $req->id, 'producto_id' => $producto->id, 'descripcion' => 'Viejo', 'codigo_producto' => 'OLD-1',
    ]);

    $this->actingAs($this->compras)
        ->patch("/admin/costos/requisiciones/detalles/{$detalle->id}/producto", ['descripcion' => 'Nuevo nombre', 'codigo' => 'NEW-1'])
        ->assertRedirect();

    expect($producto->fresh()->descripcion)->toBe('Nuevo nombre')
        ->and($producto->fresh()->codigo)->toBe('NEW-1')
        ->and($detalle->fresh()->descripcion)->toBe('Nuevo nombre')
        ->and($detalle->fresh()->codigo_producto)->toBe('NEW-1');
});
