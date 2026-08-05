<?php

use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\ProcesoEvento;
use App\Models\Prod\Registro;
use App\Models\Prod\Ubicacion;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->destajo = Destajo::factory()->create([
        'fecha_inicio' => '2026-02-03',
        'fecha_fin' => '2026-02-09',
    ]);

    $this->soldadura = proceso();
    $this->pintura = proceso('Pintura');

    $this->marca = marcaConPiezas(3, ['marca' => 'TG-CM5-1']);
    obraPagaProcesos($this->marca->obra_id, $this->soldadura, $this->pintura);

    $this->pieza = $this->marca->piezas[0];
});

/** Encabezado real del export de planta, recortado a lo que se usa. */
function encabezadoExport(): string
{
    return "Proceso,Contracto,Movido el,Ubicacion,QS,Marca,Peso,Cantidad,Trabajador\n";
}

function renglonExport(string $proceso, string $ubicacion, string $qs, string $marca = 'TG-CM5-1'): string
{
    return "{$proceso},S26-05-05 REJAS,46195,{$ubicacion},{$qs},{$marca},1746,1,SAUL DZUL\n";
}

function subirExport(string $csv)
{
    return test()->actingAs(test()->user)
        ->post(route('admin.prod.destajos.registros.import-csv', test()->destajo), [
            'csv_file' => UploadedFile::fake()->createWithContent('avance.csv', $csv),
            'fecha' => '2026-02-05',
        ]);
}

function grupoConUbicacion(string $ubicacion, string $descripcion = 'Cuadrilla A'): GrupoTrabajo
{
    $grupo = GrupoTrabajo::factory()->create(['descripcion' => $descripcion]);
    $grupo->ubicaciones()->attach(Ubicacion::factory()->create(['nombre' => $ubicacion]));

    return $grupo;
}

test('el evento decide el proceso y el QS decide la pieza', function () {
    $grupo = grupoConUbicacion('M3.6 Fabricacion');

    $csv = encabezadoExport()
        .renglonExport('75 Soldadura', 'M3.6 Fabricacion', $this->pieza->qs);

    subirExport($csv)->assertSessionHasNoErrors();

    $registro = Registro::sole();

    expect($registro->pieza_id)->toBe($this->pieza->id)
        ->and($registro->proceso_id)->toBe($this->soldadura->id)
        ->and($registro->grupo_trabajo_id)->toBe($grupo->id)
        ->and($registro->fecha->toDateString())->toBe('2026-02-05');
});

test('cada proceso entra por su propio evento', function () {
    grupoConUbicacion('M3.6 Fabricacion');

    $csv = encabezadoExport()
        .renglonExport('75 Soldadura', 'M3.6 Fabricacion', $this->pieza->qs)
        .renglonExport('85 Pintura', 'M3.6 Fabricacion', $this->pieza->qs);

    subirExport($csv)->assertSessionHasNoErrors();

    expect(Registro::where('proceso_id', $this->soldadura->id)->count())->toBe(1)
        ->and(Registro::where('proceso_id', $this->pintura->id)->count())->toBe(1);
});

test('los eventos que no pagan destajo se ignoran sin ruido', function () {
    grupoConUbicacion('M3.6 Fabricacion');

    $csv = encabezadoExport()
        .renglonExport('75 Soldadura', 'M3.6 Fabricacion', $this->pieza->qs)
        // Corte, inspeccion y embarque son la mayoria del archivo.
        .renglonExport('10 Corte', 'M3.6 Fabricacion', $this->marca->piezas[1]->qs)
        .renglonExport('60 Inspección', 'M3.6 Fabricacion', $this->marca->piezas[2]->qs);

    subirExport($csv)->assertSessionHasNoErrors();

    expect(Registro::count())->toBe(1);
});

test('el mismo QS repetido en el mismo evento se paga una sola vez', function () {
    grupoConUbicacion('M3.3 Fabricacion');

    $csv = encabezadoExport()
        .renglonExport('75 Soldadura', 'M3.3 Fabricacion', $this->pieza->qs)
        .renglonExport('75 Soldadura', 'M3.3 Fabricacion', $this->pieza->qs);

    subirExport($csv)->assertSessionHasNoErrors();

    expect(Registro::count())->toBe(1);
});

test('la marca repetida ya no es ambigua: manda el QS', function () {
    grupoConUbicacion('M3.6 Fabricacion');

    // Otra etapa con la misma marca; el export sólo necesita el QS.
    $etapa2 = marcaConPiezas(2, [
        'obra_id' => $this->marca->obra_id,
        'catalogo_id' => $this->marca->catalogo_id,
        'marca' => 'TG-CM5-1',
        'etapa' => '2',
    ]);

    $csv = encabezadoExport()
        .renglonExport('75 Soldadura', 'M3.6 Fabricacion', $etapa2->piezas[0]->qs);

    subirExport($csv)->assertSessionHasNoErrors();

    expect(Registro::sole()->pieza_id)->toBe($etapa2->piezas[0]->id);
});

test('cruza la ubicacion sin importar acentos ni mayusculas', function () {
    $grupo = grupoConUbicacion('M1.4 Fabricación');

    $csv = encabezadoExport().renglonExport('75 Soldadura', 'm1.4  FABRICACION', $this->pieza->qs);

    subirExport($csv)->assertSessionHasNoErrors();

    expect(Registro::sole()->grupo_trabajo_id)->toBe($grupo->id);
});

test('reporta la ubicacion que no esta en el catalogo', function () {
    $csv = encabezadoExport().renglonExport('75 Soldadura', '2T Modulo 9.9', $this->pieza->qs);

    subirExport($csv)->assertSessionHasErrors('csv_file');

    expect(Registro::count())->toBe(0);
});

test('reporta la ubicacion sin grupo asignado', function () {
    Ubicacion::factory()->create(['nombre' => 'M2.1 Fabricacion']);

    $csv = encabezadoExport().renglonExport('75 Soldadura', 'M2.1 Fabricacion', $this->pieza->qs);

    subirExport($csv)->assertSessionHasErrors('csv_file');

    expect(Registro::count())->toBe(0);
});

test('reporta la ubicacion que trabajan varios grupos', function () {
    $ubicacion = Ubicacion::factory()->create(['nombre' => 'M2.4 Fabricacion']);
    GrupoTrabajo::factory()->create(['descripcion' => 'Cuadrilla A'])->ubicaciones()->attach($ubicacion);
    GrupoTrabajo::factory()->create(['descripcion' => 'Cuadrilla B'])->ubicaciones()->attach($ubicacion);

    $csv = encabezadoExport().renglonExport('75 Soldadura', 'M2.4 Fabricacion', $this->pieza->qs);

    subirExport($csv)->assertSessionHasErrors('csv_file');

    expect(Registro::count())->toBe(0);
});

test('avisa cuando el archivo no trae movimientos de los eventos configurados', function () {
    grupoConUbicacion('M3.6 Fabricacion');

    $csv = encabezadoExport().renglonExport('10 Corte', 'M3.6 Fabricacion', $this->pieza->qs);

    subirExport($csv)->assertSessionHasErrors('csv_file');

    expect(Registro::count())->toBe(0);
});

test('un evento que se reasigna a otro proceso cambia a donde se paga', function () {
    grupoConUbicacion('M3.6 Fabricacion');

    // Planta renumera: el 75 pasa a ser de pintura.
    ProcesoEvento::where('evento', '75')->update(['proceso_id' => $this->pintura->id]);

    $csv = encabezadoExport().renglonExport('75 Lo que sea', 'M3.6 Fabricacion', $this->pieza->qs);

    subirExport($csv)->assertSessionHasNoErrors();

    expect(Registro::sole()->proceso_id)->toBe($this->pintura->id);
});

test('rechaza el proceso que la obra no paga', function () {
    grupoConUbicacion('M3.6 Fabricacion');
    $this->marca->obra->procesos()->detach($this->pintura->id);

    $csv = encabezadoExport().renglonExport('85 Pintura', 'M3.6 Fabricacion', $this->pieza->qs);

    subirExport($csv)->assertSessionHasErrors('csv_file');

    expect(Registro::count())->toBe(0);
});

test('el formato manual acepta grupo, QS y proceso', function () {
    GrupoTrabajo::factory()->create(['descripcion' => 'Grupo A']);

    $csv = "GRUPO,QS,PROCESO\nGrupo A,{$this->pieza->qs},Soldadura\n";

    test()->actingAs($this->user)
        ->post(route('admin.prod.destajos.registros.import-csv', $this->destajo), [
            'csv_file' => UploadedFile::fake()->createWithContent('produccion.csv', $csv),
            'fecha' => '2026-02-05',
        ])
        ->assertSessionHasNoErrors();

    expect(Registro::sole()->pieza_id)->toBe($this->pieza->id);
});

test('el formato manual reporta el proceso que no existe', function () {
    GrupoTrabajo::factory()->create(['descripcion' => 'Grupo A']);

    $csv = "GRUPO,QS,PROCESO\nGrupo A,{$this->pieza->qs},Galvanizado\n";

    test()->actingAs($this->user)
        ->post(route('admin.prod.destajos.registros.import-csv', $this->destajo), [
            'csv_file' => UploadedFile::fake()->createWithContent('produccion.csv', $csv),
            'fecha' => '2026-02-05',
        ])
        ->assertSessionHasErrors('csv_file');

    expect(Registro::count())->toBe(0);
});
