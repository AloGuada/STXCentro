<?php

use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\Pieza;
use App\Models\Prod\Registro;

/**
 * Un catálogo con una marca, dos piezas y un precio asignado.
 */
function catalogoConMarcas(): Catalogo
{
    $catalogo = Catalogo::factory()->create();
    $marca = Concepto::factory()->create([
        'catalogo_id' => $catalogo->id,
        'obra_id' => $catalogo->obra_id,
    ]);

    Pieza::factory()->count(2)->create([
        'concepto_id' => $marca->id,
        'catalogo_id' => $catalogo->id,
    ]);

    GrupoPrecioConcepto::factory()->create([
        'concepto_id' => $marca->id,
        'grupo_precio_id' => GrupoPrecio::factory()->create(['obra_id' => $catalogo->obra_id])->id,
    ]);

    return $catalogo;
}

test('el dry-run no borra nada', function () {
    $catalogo = catalogoConMarcas();

    $this->artisan('prod:vaciar-catalogo', ['catalogo' => $catalogo->id])
        ->expectsOutputToContain('DRY-RUN')
        ->assertSuccessful();

    expect(Concepto::where('catalogo_id', $catalogo->id)->count())->toBe(1)
        ->and(Pieza::where('catalogo_id', $catalogo->id)->count())->toBe(2);
});

test('vacia el catalogo pero lo conserva', function () {
    $catalogo = catalogoConMarcas();

    $this->artisan('prod:vaciar-catalogo', ['catalogo' => $catalogo->id, '--force' => true])
        ->assertSuccessful();

    expect(Catalogo::whereKey($catalogo->id)->exists())->toBeTrue()
        ->and(Concepto::where('catalogo_id', $catalogo->id)->count())->toBe(0)
        ->and(Pieza::where('catalogo_id', $catalogo->id)->count())->toBe(0)
        ->and(GrupoPrecioConcepto::count())->toBe(0);
});

test('no vacia un catalogo con produccion capturada', function () {
    $catalogo = catalogoConMarcas();
    Registro::factory()->create(['pieza_id' => Pieza::where('catalogo_id', $catalogo->id)->first()->id]);

    $this->artisan('prod:vaciar-catalogo', ['catalogo' => $catalogo->id, '--force' => true])
        ->expectsOutputToContain('producción capturada')
        ->assertFailed();

    expect(Concepto::where('catalogo_id', $catalogo->id)->count())->toBe(1);
});

test('con --con-produccion borra tambien los registros', function () {
    $catalogo = catalogoConMarcas();
    Registro::factory()->create(['pieza_id' => Pieza::where('catalogo_id', $catalogo->id)->first()->id]);

    $this->artisan('prod:vaciar-catalogo', [
        'catalogo' => $catalogo->id,
        '--con-produccion' => true,
        '--force' => true,
    ])->assertSuccessful();

    expect(Registro::count())->toBe(0)
        ->and(Pieza::where('catalogo_id', $catalogo->id)->count())->toBe(0);
});

test('avisa cuando el catalogo ya esta vacio', function () {
    $catalogo = Catalogo::factory()->create();

    $this->artisan('prod:vaciar-catalogo', ['catalogo' => $catalogo->id])
        ->expectsOutputToContain('Ya está vacío')
        ->assertSuccessful();
});

test('falla con un catalogo inexistente', function () {
    $this->artisan('prod:vaciar-catalogo', ['catalogo' => 999999])
        ->assertFailed();
});
