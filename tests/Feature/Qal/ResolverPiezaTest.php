<?php

use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Prod\Pieza;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\TipoPieza;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * El lector de QR de la captura: de lo que se leyó en la etiqueta a la pieza
 * física de Producción.
 */
function usuarioQueEscanea(): User
{
    Permission::firstOrCreate(['name' => 'qal.inspecciones.crear', 'guard_name' => 'web']);

    $usuario = User::factory()->create();
    $usuario->givePermissionTo('qal.inspecciones.crear');

    return $usuario;
}

test('encuentra la pieza por su qr y deduce el tipo por la marca', function () {
    $columna = TipoPieza::factory()->create(['prefijo' => 'CM', 'descripcion' => 'Columna metálica']);
    $concepto = Concepto::factory()->create(['marca' => 'SX-CM2-11']);
    $pieza = Pieza::factory()->create(['concepto_id' => $concepto->id]);

    $this->actingAs(usuarioQueEscanea())
        ->getJson(route('admin.qal.piezas.resolver', ['codigo' => $pieza->qr]))
        ->assertOk()
        ->assertJsonPath('id', $pieza->id)
        ->assertJsonPath('obra_id', $concepto->obra_id)
        ->assertJsonPath('concepto.marca', 'SX-CM2-11')
        ->assertJsonPath('tipo_pieza_id', $columna->id);
});

test('el catalogo congelado no cuenta: la pieza de la nave es la del vigente', function () {
    $concepto = Concepto::factory()->create();
    $pieza = Pieza::factory()->create(['concepto_id' => $concepto->id]);
    Catalogo::query()->whereKey($concepto->catalogo_id)->update(['vigente' => false]);

    $this->actingAs(usuarioQueEscanea())
        ->getJson(route('admin.qal.piezas.resolver', ['codigo' => $pieza->qr]))
        ->assertNotFound();
});

test('un qs que existe en dos obras es ambiguo hasta elegir la obra', function () {
    $una = Pieza::factory()->create(['qs' => '4711']);
    Pieza::factory()->create(['qs' => '4711']);
    $usuario = usuarioQueEscanea();

    $this->actingAs($usuario)
        ->getJson(route('admin.qal.piezas.resolver', ['codigo' => '4711']))
        ->assertStatus(409)
        ->assertJsonCount(2, 'candidatas');

    $this->actingAs($usuario)
        ->getJson(route('admin.qal.piezas.resolver', ['codigo' => '4711', 'obra_id' => $una->catalogo->obra_id]))
        ->assertOk()
        ->assertJsonPath('id', $una->id);
});

test('trae las inspecciones previas de la pieza', function () {
    $pieza = Pieza::factory()->create();
    Inspeccion::factory()->dePieza($pieza, FaseTransformacion::Segunda, Subetapa::Soldado)->rechazada()->create();

    $this->actingAs(usuarioQueEscanea())
        ->getJson(route('admin.qal.piezas.resolver', ['codigo' => $pieza->qr]))
        ->assertOk()
        ->assertJsonPath('inspecciones.0.estatus', 'rechazado')
        ->assertJsonPath('inspecciones.0.subetapa', 'soldado')
        ->assertJsonPath('inspecciones.0.numero_inspeccion', 1);
});

test('escanear pide el permiso de capturar', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('admin.qal.piezas.resolver', ['codigo' => 'QR-1']))
        ->assertForbidden();
});
