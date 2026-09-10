<?php

use App\Models\Obra as ObraDelPortal;
use App\Models\Prod\Catalogo;
use App\Models\Qal\Obra;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * La obra de Calidad es la obra del portal, la misma de Producción.
 *
 * Se comprueba que Calidad no guarda su propio número ni descripción, que una
 * obra del portal tiene a lo más una ficha de Calidad, y que la obra entra a
 * Calidad en cuanto Producción abre su catálogo.
 */
test('numero y descripcion se leen de la obra del portal', function () {
    $portal = ObraDelPortal::factory()->create(['no' => 'S26-01', 'descripcion' => 'Nave Six', 'activa' => true]);
    $obra = Obra::paraObra($portal);

    expect($obra->fresh()->no)->toBe('S26-01')
        ->and($obra->fresh()->descripcion)->toBe('Nave Six')
        ->and($obra->fresh()->activa)->toBeTrue();

    $listada = Obra::query()->conDatosDeLaObra()->firstWhere('qal_obras.id', $obra->id);

    expect($listada->toArray())->toMatchArray(['no' => 'S26-01', 'descripcion' => 'Nave Six', 'activa' => true]);

    $portal->update(['descripcion' => 'Nave Six · ampliación']);

    expect($obra->fresh()->descripcion)->toBe('Nave Six · ampliación');
});

test('una obra del portal tiene a lo mas una ficha de calidad', function () {
    $portal = ObraDelPortal::factory()->create();

    expect(Obra::paraObra($portal)->id)->toBe(Obra::paraObra($portal->id)->id);

    Obra::query()->create(['obra_id' => $portal->id]);
})->throws(UniqueConstraintViolationException::class);

test('abrir el catalogo de produccion da de alta la obra en calidad una sola vez', function () {
    $portal = ObraDelPortal::factory()->create();

    Catalogo::factory()->create(['obra_id' => $portal->id, 'version' => 1]);
    Catalogo::factory()->create(['obra_id' => $portal->id, 'version' => 2]);

    expect(Obra::query()->where('obra_id', $portal->id)->count())->toBe(1);
});

test('una obra inactiva en el portal queda fuera de las activas de calidad', function () {
    $activa = Obra::factory()->create();
    $inactiva = Obra::factory()->inactiva()->create();

    expect(Obra::query()->activas()->pluck('id')->all())
        ->toContain($activa->id)
        ->not->toContain($inactiva->id);
});

test('la migracion liga por numero y da de alta las obras con catalogo', function () {
    $migracion = require database_path('migrations/2026_09_10_100000_qal_obras_pasa_a_extension_de_obras.php');
    $migracion->down();

    $existente = ObraDelPortal::factory()->create(['no' => 'T4-CANCUN']);
    DB::table('qal_obras')->insert(['no' => 'T4-CANCUN', 'descripcion' => 'T4', 'activa' => true]);
    DB::table('qal_obras')->insert(['no' => 'SOLO-CALIDAD', 'descripcion' => 'Sólo en calidad', 'activa' => true]);

    $conCatalogo = ObraDelPortal::factory()->create();
    DB::table('prod_catalogos')->insert([
        'obra_id' => $conCatalogo->id, 'nombre' => 'Catálogo', 'version' => 1, 'vigente' => true,
    ]);

    $migracion->up();

    expect(Obra::query()->where('obra_id', $existente->id)->exists())->toBeTrue()
        ->and(ObraDelPortal::query()->where('no', 'SOLO-CALIDAD')->exists())->toBeTrue()
        ->and(Obra::query()->where('obra_id', $conCatalogo->id)->exists())->toBeTrue();
});
