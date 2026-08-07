<?php

use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\Pieza;

/**
 * El destrozo que dejaba el import viejo: la misma marca dos veces, la vieja sin
 * lote y sin piezas pero con su cantidad y su precio, la nueva con las piezas.
 *
 * @return array{0: Catalogo, 1: Concepto, 2: Concepto}
 */
function catalogoConMarcaDuplicada(): array
{
    $catalogo = Catalogo::factory()->create();

    $huerfana = Concepto::factory()->create([
        'catalogo_id' => $catalogo->id,
        'obra_id' => $catalogo->obra_id,
        'marca' => 'TG-BAR-1',
        'lote' => null,
        'cantidad' => 92,
    ]);

    $viva = Concepto::factory()->create([
        'catalogo_id' => $catalogo->id,
        'obra_id' => $catalogo->obra_id,
        'marca' => 'TG-BAR-1',
        'lote' => '1',
        'cantidad' => 2,
    ]);

    Pieza::factory()->count(2)->create([
        'catalogo_id' => $catalogo->id,
        'concepto_id' => $viva->id,
    ]);

    return [$catalogo, $huerfana, $viva];
}

test('el dry-run reporta pero no borra', function () {
    [$catalogo, $huerfana] = catalogoConMarcaDuplicada();

    $this->artisan('prod:limpiar-marcas-duplicadas', ['catalogo' => $catalogo->id])
        ->expectsOutputToContain('DRY-RUN')
        ->assertSuccessful();

    expect(Concepto::find($huerfana->id))->not->toBeNull();
});

test('borra la marca duplicada vacia y conserva la que tiene piezas', function () {
    [$catalogo, $huerfana, $viva] = catalogoConMarcaDuplicada();

    $this->artisan('prod:limpiar-marcas-duplicadas', ['catalogo' => $catalogo->id, '--force' => true])
        ->assertSuccessful();

    expect(Concepto::find($huerfana->id))->toBeNull()
        ->and(Concepto::find($viva->id))->not->toBeNull()
        ->and($viva->piezas()->count())->toBe(2);
});

test('el grupo de precio de la marca borrada pasa a la que se queda', function () {
    [$catalogo, $huerfana, $viva] = catalogoConMarcaDuplicada();

    $grupo = GrupoPrecio::factory()->create(['obra_id' => $catalogo->obra_id]);
    GrupoPrecioConcepto::factory()->create([
        'concepto_id' => $huerfana->id,
        'grupo_precio_id' => $grupo->id,
    ]);

    $this->artisan('prod:limpiar-marcas-duplicadas', ['catalogo' => $catalogo->id, '--force' => true])
        ->assertSuccessful();

    expect(GrupoPrecioConcepto::where('concepto_id', $viva->id)->where('grupo_precio_id', $grupo->id)->exists())
        ->toBeTrue()
        ->and(GrupoPrecioConcepto::where('concepto_id', $huerfana->id)->exists())->toBeFalse();
});

test('no toca las marcas repetidas donde las dos tienen piezas', function () {
    $catalogo = Catalogo::factory()->create();

    foreach (['LOTE A', 'LOTE B'] as $lote) {
        $marca = Concepto::factory()->create([
            'catalogo_id' => $catalogo->id,
            'obra_id' => $catalogo->obra_id,
            'marca' => 'TG-BAR-1',
            'lote' => $lote,
        ]);
        Pieza::factory()->create(['catalogo_id' => $catalogo->id, 'concepto_id' => $marca->id]);
    }

    $this->artisan('prod:limpiar-marcas-duplicadas', ['catalogo' => $catalogo->id, '--force' => true])
        ->assertSuccessful();

    expect(Concepto::where('catalogo_id', $catalogo->id)->count())->toBe(2);
});

test('avisa cuando el catalogo no existe', function () {
    $this->artisan('prod:limpiar-marcas-duplicadas', ['catalogo' => 999999])
        ->assertFailed();
});
