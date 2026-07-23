<?php

use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionOpcion;
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

test('con varias opciones por proveedor el total usa el mínimo por partida', function () {
    $req = Requisicion::factory()->create();
    $p1 = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 10]);
    $p2 = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 5]);
    $prov = Proveedor::factory()->create();
    $op1 = RequisicionCotizacionOpcion::create(['requisicion_id' => $req->id, 'proveedor_id' => $prov->id, 'orden' => 1]);
    $op2 = RequisicionCotizacionOpcion::create(['requisicion_id' => $req->id, 'proveedor_id' => $prov->id, 'orden' => 2]);

    // p1: dos opciones (100 y 80) → gana 80 × 10 = 800.
    RequisicionCotizacionPrecio::factory()->create(['requisicion_detalle_id' => $p1->id, 'proveedor_id' => $prov->id, 'opcion_id' => $op1->id, 'precio_unitario' => 100]);
    RequisicionCotizacionPrecio::factory()->create(['requisicion_detalle_id' => $p1->id, 'proveedor_id' => $prov->id, 'opcion_id' => $op2->id, 'precio_unitario' => 80]);
    // p2: una opción (20) → 20 × 5 = 100.
    RequisicionCotizacionPrecio::factory()->create(['requisicion_detalle_id' => $p2->id, 'proveedor_id' => $prov->id, 'opcion_id' => $op1->id, 'precio_unitario' => 20]);

    $mejor = $this->buscador->buscar($req->refresh());

    expect($mejor['id'])->toBe($prov->id);
    expect($mejor['total'])->toBe(900.0); // 800 + 100, no la suma de todas las opciones
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

test('buscarLote resuelve varias requisiciones y coincide con buscar', function () {
    $reqA = Requisicion::factory()->create();
    $a1 = RequisicionDetalle::factory()->create(['requisicion_id' => $reqA->id, 'cantidad' => 10]);
    $a2 = RequisicionDetalle::factory()->create(['requisicion_id' => $reqA->id, 'cantidad' => 5]);
    $barato = Proveedor::factory()->create();
    $caro = Proveedor::factory()->create();
    cotizar($a1, $barato, 100);
    cotizar($a2, $barato, 20);  // total 1100
    cotizar($a1, $caro, 120);
    cotizar($a2, $caro, 30);    // total 1350

    $reqB = Requisicion::factory()->create();
    $b1 = RequisicionDetalle::factory()->create(['requisicion_id' => $reqB->id, 'cantidad' => 2]);
    $provB = Proveedor::factory()->create();
    cotizar($b1, $provB, 25);   // total 50

    $sinPartidas = Requisicion::factory()->create();

    $incompleta = Requisicion::factory()->create();
    $c1 = RequisicionDetalle::factory()->create(['requisicion_id' => $incompleta->id, 'cantidad' => 1]);
    RequisicionDetalle::factory()->create(['requisicion_id' => $incompleta->id, 'cantidad' => 1]);
    cotizar($c1, Proveedor::factory()->create(), 10);

    $lote = $this->buscador->buscarLote([$reqA->id, $reqB->id, $sinPartidas->id, $incompleta->id]);

    expect($lote[$reqA->id]['id'])->toBe($barato->id);
    expect($lote[$reqA->id]['total'])->toBe(1100.0);
    expect($lote[$reqB->id]['id'])->toBe($provB->id);
    expect($lote[$reqB->id]['total'])->toBe(50.0);
    expect($lote[$sinPartidas->id])->toBeNull();
    expect($lote[$incompleta->id])->toBeNull();

    expect($lote[$reqA->id])->toBe($this->buscador->buscar($reqA->refresh()));
    expect($lote[$reqB->id])->toBe($this->buscador->buscar($reqB->refresh()));
});

test('buscarLote ejecuta un número constante de queries sin importar el tamaño del lote', function () {
    $ids = [];
    foreach (range(1, 5) as $i) {
        $req = Requisicion::factory()->create();
        $d = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 1]);
        cotizar($d, Proveedor::factory()->create(), 10);
        $ids[] = $req->id;
    }

    \Illuminate\Support\Facades\DB::enableQueryLog();
    $this->buscador->buscarLote($ids);
    $queries = count(\Illuminate\Support\Facades\DB::getQueryLog());
    \Illuminate\Support\Facades\DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(2);
});

test('el listado usa el lote y no recalcula por fila', function () {
    $req = Requisicion::factory()->create();
    $d = RequisicionDetalle::factory()->create(['requisicion_id' => $req->id, 'cantidad' => 4]);
    $prov = Proveedor::factory()->create();
    cotizar($d, $prov, 5);

    $req->refresh()->precargarMejorProveedor(['id' => $prov->id, 'razon_social' => 'X', 'nombre_comercial' => null, 'total' => 99.0]);

    expect($req->mejor_proveedor['total'])->toBe(99.0);
});
