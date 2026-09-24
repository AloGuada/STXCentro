<?php

use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Pieza;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Obra;
use App\Models\Qal\Programacion;
use App\Models\Qal\ProgramacionPieza;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * El formulario «agregar al plan» del avance de producción: las marcas de la
 * obra, las piezas de una marca con su estado frente al plan, y la pieza de un
 * QR, que rellena obra, lote y marca.
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

test('las piezas de una marca llegan por su qs, con su estado frente al plan de la semana', function () {
    $marca = Concepto::factory()->create();
    Obra::paraObra($marca->obra);
    $grupo = GrupoTrabajo::factory()->create();
    $piezas = collect(['1' => '155001', '2' => '155002', '3' => '155003', '4' => '155004', '5' => '155005'])
        ->map(fn (string $qr, string $qs): Pieza => Pieza::factory()->create(['concepto_id' => $marca->id, 'qr' => $qr, 'qs' => $qs]));
    $pasada = Programacion::factory()->cerrada()->create(['obra_id' => $marca->obra_id, 'anio' => 2026, 'semana' => 33]);
    $borradorViejo = Programacion::factory()->create(['obra_id' => $marca->obra_id, 'anio' => 2026, 'semana' => 32]);
    $actual = Programacion::factory()->create(['obra_id' => $marca->obra_id, 'anio' => 2026, 'semana' => 34]);
    $enElPlan = fn (Programacion $plan, Pieza $pieza) => ProgramacionPieza::factory()->dePieza($pieza)
        ->for($plan, 'programacion')->for($grupo, 'grupoTrabajo');

    $enElPlan($actual, $piezas['2'])->create();
    $enElPlan($pasada, $piezas['3'])->create();
    // Un borrador que nunca se cerró no deja nada pendiente.
    $enElPlan($borradorViejo, $piezas['4'])->create();
    $enElPlan($pasada, $piezas['5'])->create();
    Inspeccion::factory()->dePieza($piezas['5'], FaseTransformacion::Segunda, Subetapa::Soldado)->create();

    $this->actingAs(quienProgramaPiezas())
        ->getJson(route('admin.qal.avance.piezas', ['marca' => $marca->id, 'fase' => '2', 'semana' => '2026-S34']))
        ->assertExactJson([
            ['id' => $piezas['1']->id, 'qr' => '155001', 'qs' => '1', 'estado' => 'libre'],
            ['id' => $piezas['2']->id, 'qr' => '155002', 'qs' => '2', 'estado' => 'en_plan'],
            ['id' => $piezas['3']->id, 'qr' => '155003', 'qs' => '3', 'estado' => 'pendiente'],
            ['id' => $piezas['4']->id, 'qr' => '155004', 'qs' => '4', 'estado' => 'libre'],
            ['id' => $piezas['5']->id, 'qr' => '155005', 'qs' => '5', 'estado' => 'fabricada'],
        ]);
});

test('buscar por qr rellena la obra, el lote y la marca', function () {
    $marca = Concepto::factory()->create(['marca' => 'SX-TP2-7', 'lote' => 'L4']);
    $pieza = Pieza::factory()->create(['concepto_id' => $marca->id, 'qr' => '155745', 'qs' => '4']);

    $this->actingAs(quienProgramaPiezas())
        ->getJson(route('admin.qal.avance.pieza', ['codigo' => '155745']))
        ->assertOk()
        ->assertExactJson([
            'obra_id' => $marca->obra_id,
            'marca_id' => $marca->id,
            'marca' => 'SX-TP2-7',
            'lote' => 'L4',
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
