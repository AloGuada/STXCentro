<?php

use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\Pieza;
use App\Models\Prod\Registro;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Obra as ObraDeCalidad;
use App\Models\Qal\ObraIncidencia;
use App\Models\User;
use Illuminate\Http\UploadedFile;

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
        ->and(Pieza::where('catalogo_id', $catalogo->id)->count())->toBe(0)
        ->and(Concepto::where('catalogo_id', $catalogo->id)->count())->toBe(0);
});

test('tras vaciarlo, volver a subir el layout no deja marcas repetidas', function () {
    $catalogo = catalogoConMarcas();

    $this->artisan('prod:vaciar-catalogo', ['catalogo' => $catalogo->id, '--force' => true])
        ->assertSuccessful();

    $subir = fn () => test()->actingAs(User::factory()->create())
        ->post(route('admin.prod.catalogos.import-csv', $catalogo), [
            'csv_file' => UploadedFile::fake()->createWithContent(
                'layout.csv',
                "QR,MARCA,DESCRIPCION,CATEGORIA,QS,CANTIDAD,PESO KG,AREA,LONGITUD MM,LOTE\n"
                    ."QR-01,TG-BAR-1,OC-BAR,Barandales,1001,1,10,1,1000,1\n",
            ),
        ]);

    $subir();
    $subir();

    expect(Concepto::where('catalogo_id', $catalogo->id)->count())->toBe(1)
        ->and(Pieza::where('catalogo_id', $catalogo->id)->count())->toBe(1);
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

test('sin --eliminar el catalogo y la obra en calidad siguen ahi', function () {
    $catalogo = catalogoConMarcas();

    $this->artisan('prod:vaciar-catalogo', ['catalogo' => $catalogo->id, '--force' => true])
        ->assertSuccessful();

    expect(Catalogo::whereKey($catalogo->id)->exists())->toBeTrue()
        ->and(ObraDeCalidad::where('obra_id', $catalogo->obra_id)->exists())->toBeTrue();
});

test('con --eliminar borra el catalogo y saca la obra de calidad', function () {
    $catalogo = catalogoConMarcas();

    expect(ObraDeCalidad::where('obra_id', $catalogo->obra_id)->exists())->toBeTrue();

    $this->artisan('prod:vaciar-catalogo', [
        'catalogo' => $catalogo->id,
        '--eliminar' => true,
        '--force' => true,
    ])->expectsOutputToContain('salió de Calidad')->assertSuccessful();

    expect(Catalogo::whereKey($catalogo->id)->exists())->toBeFalse()
        ->and(ObraDeCalidad::where('obra_id', $catalogo->obra_id)->exists())->toBeFalse()
        ->and(Concepto::where('catalogo_id', $catalogo->id)->count())->toBe(0)
        ->and(Pieza::where('catalogo_id', $catalogo->id)->count())->toBe(0);
});

test('con --eliminar borra un catalogo que ya estaba vacio', function () {
    $catalogo = Catalogo::factory()->create();

    $this->artisan('prod:vaciar-catalogo', [
        'catalogo' => $catalogo->id,
        '--eliminar' => true,
        '--force' => true,
    ])->assertSuccessful();

    expect(Catalogo::whereKey($catalogo->id)->exists())->toBeFalse();
});

test('el dry-run con --eliminar no borra el catalogo', function () {
    $catalogo = catalogoConMarcas();

    $this->artisan('prod:vaciar-catalogo', ['catalogo' => $catalogo->id, '--eliminar' => true])
        ->expectsOutputToContain('DRY-RUN')
        ->assertSuccessful();

    expect(Catalogo::whereKey($catalogo->id)->exists())->toBeTrue()
        ->and(ObraDeCalidad::where('obra_id', $catalogo->obra_id)->exists())->toBeTrue();
});

test('se cancela entero si calidad ya inspecciono la obra', function () {
    $catalogo = catalogoConMarcas();
    Inspeccion::factory()->create(['obra_id' => $catalogo->obra_id]);

    $this->artisan('prod:vaciar-catalogo', [
        'catalogo' => $catalogo->id,
        '--eliminar' => true,
        '--force' => true,
    ])->expectsOutputToContain('Calidad ya trabajó esta obra')->assertFailed();

    expect(Catalogo::whereKey($catalogo->id)->exists())->toBeTrue()
        ->and(Concepto::where('catalogo_id', $catalogo->id)->count())->toBe(1)
        ->and(Pieza::where('catalogo_id', $catalogo->id)->count())->toBe(2);
});

test('se cancela si calidad tiene incidencias de la obra aunque no haya inspecciones', function () {
    $catalogo = catalogoConMarcas();
    ObraIncidencia::factory()->create([
        'qal_obra_id' => ObraDeCalidad::where('obra_id', $catalogo->obra_id)->value('id'),
    ]);

    $this->artisan('prod:vaciar-catalogo', [
        'catalogo' => $catalogo->id,
        '--eliminar' => true,
        '--force' => true,
    ])->expectsOutputToContain('Calidad ya trabajó esta obra')->assertFailed();

    expect(Catalogo::whereKey($catalogo->id)->exists())->toBeTrue();
});

test('con otra version viva solo estorban las inspecciones de esa version', function () {
    $catalogo = catalogoConMarcas();
    $v2 = Catalogo::factory()->create([
        'obra_id' => $catalogo->obra_id,
        'catalogo_origen_id' => $catalogo->id,
        'version' => 2,
    ]);
    // La inspección cuelga de la v2: la v1 se puede ir.
    Inspeccion::factory()->create(['obra_id' => $catalogo->obra_id, 'catalogo_id' => $v2->id]);

    $this->artisan('prod:vaciar-catalogo', [
        'catalogo' => $catalogo->id,
        '--eliminar' => true,
        '--force' => true,
    ])->expectsOutputToContain('sin origen')->assertSuccessful();

    expect(Catalogo::whereKey($catalogo->id)->exists())->toBeFalse()
        // La obra sigue en Calidad: le queda catálogo.
        ->and(ObraDeCalidad::where('obra_id', $catalogo->obra_id)->exists())->toBeTrue()
        ->and(Catalogo::whereKey($v2->id)->value('catalogo_origen_id'))->toBeNull();
});

test('se cancela si la version que se borra tiene inspecciones propias', function () {
    $catalogo = catalogoConMarcas();
    Catalogo::factory()->create(['obra_id' => $catalogo->obra_id, 'version' => 2]);
    Inspeccion::factory()->create(['obra_id' => $catalogo->obra_id, 'catalogo_id' => $catalogo->id]);

    $this->artisan('prod:vaciar-catalogo', [
        'catalogo' => $catalogo->id,
        '--eliminar' => true,
        '--force' => true,
    ])->expectsOutputToContain('Calidad ya trabajó esta obra')->assertFailed();

    expect(Catalogo::whereKey($catalogo->id)->exists())->toBeTrue();
});
