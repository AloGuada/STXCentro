<?php

use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Prod\Pieza;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * El formulario «agregar al plan» del avance de producción: las marcas de la
 * obra, las piezas de una marca y la pieza de un QR, que rellena obra y marca.
 */
function quienProgramaPiezas(array $permisos = ['qal.programacion.ver', 'qal.programacion.capturar']): User
{
    foreach ($permisos as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo($permisos);

    return $usuario;
}

test('solo quien captura el plan usa el formulario', function () {
    $marca = Concepto::factory()->create();

    $this->actingAs(quienProgramaPiezas(['qal.programacion.ver']))
        ->getJson(route('admin.qal.avance.marcas', ['obra' => $marca->obra_id]))
        ->assertForbidden();
});

test('las marcas de la obra llegan en orden natural, sin las del catalogo congelado ni las inactivas', function () {
    $diez = Concepto::factory()->create(['marca' => 'SX-CM1-10']);
    $dos = Concepto::factory()->create(['obra_id' => $diez->obra_id, 'catalogo_id' => $diez->catalogo_id, 'marca' => 'SX-CM1-2']);
    Concepto::factory()->create(['obra_id' => $diez->obra_id, 'catalogo_id' => $diez->catalogo_id, 'marca' => 'SX-CM1-3', 'activo' => false]);
    Pieza::factory()->count(3)->create(['concepto_id' => $dos->id]);
    // Una versión anterior, congelada, del catálogo de la misma obra.
    $anterior = Catalogo::query()->create(['obra_id' => $diez->obra_id, 'version' => 0, 'nombre' => 'Versión anterior', 'vigente' => false]);
    Concepto::factory()->create(['obra_id' => $diez->obra_id, 'catalogo_id' => $anterior->id, 'marca' => 'SX-CM1-1']);

    $this->actingAs(quienProgramaPiezas())
        ->getJson(route('admin.qal.avance.marcas', ['obra' => $diez->obra_id]))
        ->assertOk()
        ->assertExactJson([
            ['id' => $dos->id, 'marca' => 'SX-CM1-2', 'lote' => $dos->lote, 'piezas' => 3],
            ['id' => $diez->id, 'marca' => 'SX-CM1-10', 'lote' => $diez->lote, 'piezas' => 0],
        ]);
});

test('las piezas de una marca llegan por su qs', function () {
    $marca = Concepto::factory()->create();
    $segunda = Pieza::factory()->create(['concepto_id' => $marca->id, 'qr' => '155002', 'qs' => '2']);
    $primera = Pieza::factory()->create(['concepto_id' => $marca->id, 'qr' => '155001', 'qs' => '1']);

    $this->actingAs(quienProgramaPiezas())
        ->getJson(route('admin.qal.avance.piezas', ['marca' => $marca->id]))
        ->assertExactJson([
            ['id' => $primera->id, 'qr' => '155001', 'qs' => '1'],
            ['id' => $segunda->id, 'qr' => '155002', 'qs' => '2'],
        ]);
});

test('buscar por qr rellena la obra y la marca', function () {
    $marca = Concepto::factory()->create(['marca' => 'SX-TP2-7']);
    $pieza = Pieza::factory()->create(['concepto_id' => $marca->id, 'qr' => '155745', 'qs' => '4']);

    $this->actingAs(quienProgramaPiezas())
        ->getJson(route('admin.qal.avance.pieza', ['codigo' => '155745']))
        ->assertOk()
        ->assertExactJson([
            'obra_id' => $marca->obra_id,
            'marca_id' => $marca->id,
            'marca' => 'SX-TP2-7',
            'pieza_id' => $pieza->id,
            'qr' => '155745',
            'qs' => '4',
        ]);
});

test('un codigo que no existe es 404 y un qs repetido entre obras pide el qr', function () {
    Pieza::factory()->create(['concepto_id' => Concepto::factory()->create()->id, 'qs' => '7']);
    Pieza::factory()->create(['concepto_id' => Concepto::factory()->create()->id, 'qs' => '7']);
    $this->actingAs(quienProgramaPiezas());

    $this->getJson(route('admin.qal.avance.pieza', ['codigo' => 'NO-EXISTE']))->assertNotFound();
    $this->getJson(route('admin.qal.avance.pieza', ['codigo' => '7']))
        ->assertStatus(409)
        ->assertJsonCount(2, 'candidatas');
});
