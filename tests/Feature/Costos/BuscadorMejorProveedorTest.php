<?php

use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Proveedor;
use App\Services\Costos\BuscadorMejorProveedor;

beforeEach(function () {
    $this->buscador = app(BuscadorMejorProveedor::class);
});

function cotizar(RequisicionDetalle $detalle, Proveedor $proveedor, float $precio): void
{
    RequisicionCotizacionPrecio::factory()->create([
        'requisicion_detalle_id' => $detalle->id,
        'proveedor_id' => $proveedor->id,
        'precio_unitario' => $precio,
    ]);
}

test('elige el proveedor con menor suma de cantidad x precio sobre todas las partidas', function () {
    $req = Requisicion::factory()->create();
    $p1 = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 10]);
    $p2 = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 5]);

    $barato = Proveedor::factory()->create();
    $caro = Proveedor::factory()->create();

    cotizar($p1, $barato, 100); // 10*100 = 1000
    cotizar($p2, $barato, 20);  //  5*20  =  100  => total 1100
    cotizar($p1, $caro, 120);   // 10*120 = 1200
    cotizar($p2, $caro, 30);    //  5*30  =  150  => total 1350

    $mejor = $this->buscador->buscar($req->refresh());

    expect($mejor['id'])->toBe($barato->id);
    expect($mejor['total'])->toBe(1100.0);
});

test('ignora proveedores que no cotizaron todas las partidas', function () {
    $req = Requisicion::factory()->create();
    $p1 = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 10]);
    $p2 = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 10]);

    $completo = Proveedor::factory()->create();
    $incompleto = Proveedor::factory()->create();

    cotizar($p1, $completo, 100);
    cotizar($p2, $completo, 100); // total 2000, cotiza ambas
    cotizar($p1, $incompleto, 1); // solo una partida, aunque sea baratísimo

    $mejor = $this->buscador->buscar($req->refresh());

    expect($mejor['id'])->toBe($completo->id);
    expect($mejor['total'])->toBe(2000.0);
});

test('en empate gana el proveedor de id menor', function () {
    $req = Requisicion::factory()->create();
    $p1 = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 10]);

    $primero = Proveedor::factory()->create();
    $segundo = Proveedor::factory()->create();

    cotizar($p1, $primero, 50);
    cotizar($p1, $segundo, 50);

    $mejor = $this->buscador->buscar($req->refresh());

    expect($mejor['id'])->toBe(min($primero->id, $segundo->id));
    expect($mejor['total'])->toBe(500.0);
});

test('devuelve null cuando la requisicion no tiene partidas', function () {
    $req = Requisicion::factory()->create();

    expect($this->buscador->buscar($req))->toBeNull();
});

test('devuelve null cuando ningun proveedor cotizo todas las partidas', function () {
    $req = Requisicion::factory()->create();
    $p1 = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 1]);
    RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 1]);

    cotizar($p1, Proveedor::factory()->create(), 10); // solo cotiza p1

    expect($this->buscador->buscar($req->refresh()))->toBeNull();
});

test('el accessor mejor_proveedor delega en el servicio', function () {
    $req = Requisicion::factory()->create();
    $p1 = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 2]);
    $prov = Proveedor::factory()->create();
    cotizar($p1, $prov, 25);

    expect($req->refresh()->mejor_proveedor['id'])->toBe($prov->id);
    expect($req->mejor_proveedor['total'])->toBe(50.0);
});
