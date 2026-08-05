<?php

use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoTrabajo;
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
    $this->obra = Obra::factory()->create();
});

/** Encabezado real del export de planta, recortado a lo que se usa. */
function encabezadoExport(): string
{
    return "Proceso,Contracto,Movido el,Ubicacion,Marca,Peso,Cantidad,Trabajador\n";
}

function renglonExport(string $proceso, string $ubicacion, string $marca, int $cantidad): string
{
    return "{$proceso},S26-05-05 REJAS,46195,{$ubicacion},{$marca},1746,{$cantidad},SAUL DZUL\n";
}

function subirExport(string $csv)
{
    return test()->actingAs(test()->user)
        ->post(route('admin.prod.destajos.registros.import-csv', test()->destajo), [
            'csv_file' => UploadedFile::fake()->createWithContent('avance.csv', $csv),
            'fecha' => '2026-02-05',
        ]);
}

/** Mismo export, pero con la columna Etapa que ahora manda planta. */
function encabezadoExportConEtapa(): string
{
    return "Proceso,Contracto,Movido el,Ubicacion,Marca,Etapa,Peso,Cantidad,Trabajador\n";
}

function renglonExportConEtapa(string $proceso, string $ubicacion, string $marca, string $etapa, int $cantidad): string
{
    return "{$proceso},S26-05-05 REJAS,46195,{$ubicacion},{$marca},{$etapa},1746,{$cantidad},SAUL DZUL\n";
}

/** Dos piezas distintas que comparten marca dentro del mismo catálogo. */
function dosEtapasDeLaMismaMarca(): array
{
    return [
        Concepto::factory()->create([
            'obra_id' => test()->obra->id,
            'marca' => 'TG-CM5-1',
            'etapa' => '1',
            'activo' => true,
            'cantidad' => 100,
        ]),
        Concepto::factory()->create([
            'obra_id' => test()->obra->id,
            'marca' => 'TG-CM5-1',
            'etapa' => '2',
            'activo' => true,
            'cantidad' => 100,
        ]),
    ];
}

function grupoConUbicacion(string $ubicacion, string $descripcion = 'Cuadrilla A'): GrupoTrabajo
{
    $grupo = GrupoTrabajo::factory()->create(['descripcion' => $descripcion]);
    $grupo->ubicaciones()->attach(Ubicacion::factory()->create(['nombre' => $ubicacion]));

    return $grupo;
}

test('toma solo el evento 55 y lo carga al grupo de esa ubicacion', function () {
    $grupo = grupoConUbicacion('M3.6 Fabricacion');
    Concepto::factory()->create(['obra_id' => $this->obra->id, 'marca' => 'TG-CM5-1', 'activo' => true, 'cantidad' => 100]);
    Concepto::factory()->create(['obra_id' => $this->obra->id, 'marca' => 'TG-CM5-2', 'activo' => true, 'cantidad' => 100]);

    $csv = encabezadoExport()
        .renglonExport('55 Soldadura', 'M3.6 Fabricacion', 'TG-CM5-1', 3)
        // Otros eventos del mismo archivo se ignoran.
        .renglonExport('10 Corte', 'M3.6 Fabricacion', 'TG-CM5-2', 99)
        .renglonExport('60 Inspección Soldadura', 'M3.6 Fabricacion', 'TG-CM5-2', 99);

    subirExport($csv)->assertSessionHasNoErrors();

    expect(Registro::count())->toBe(1);
    $registro = Registro::sole();
    expect($registro->cantidad)->toBe(3)
        ->and($registro->grupo_trabajo_id)->toBe($grupo->id)
        ->and($registro->fecha->toDateString())->toBe('2026-02-05');
});

test('suma los movimientos repetidos de la misma ubicacion y marca', function () {
    grupoConUbicacion('M3.3 Fabricacion');
    Concepto::factory()->create(['obra_id' => $this->obra->id, 'marca' => 'PC-RD4-1', 'activo' => true, 'cantidad' => 500]);

    $csv = encabezadoExport()
        .renglonExport('55 Soldadura', 'M3.3 Fabricacion', 'PC-RD4-1', 50)
        .renglonExport('55 Soldadura', 'M3.3 Fabricacion', 'PC-RD4-1', 100);

    subirExport($csv)->assertSessionHasNoErrors();

    expect(Registro::count())->toBe(1)
        ->and(Registro::sole()->cantidad)->toBe(150);
});

test('cruza la ubicacion sin importar acentos ni mayusculas', function () {
    $grupo = grupoConUbicacion('M1.4 Fabricación');
    Concepto::factory()->create(['obra_id' => $this->obra->id, 'marca' => 'TG-CM5-12', 'activo' => true, 'cantidad' => 50]);

    $csv = encabezadoExport().renglonExport('55 Soldadura', 'm1.4  FABRICACION', 'TG-CM5-12', 6);

    subirExport($csv)->assertSessionHasNoErrors();

    expect(Registro::sole()->grupo_trabajo_id)->toBe($grupo->id);
});

test('reporta la ubicacion que no esta en el catalogo', function () {
    Concepto::factory()->create(['obra_id' => $this->obra->id, 'marca' => 'TG-CM5-1', 'activo' => true, 'cantidad' => 50]);

    $csv = encabezadoExport().renglonExport('55 Soldadura', '2T Modulo 9.9', 'TG-CM5-1', 1);

    subirExport($csv)->assertSessionHasErrors('csv_file');

    expect(Registro::count())->toBe(0);
});

test('reporta la ubicacion sin grupo asignado', function () {
    Ubicacion::factory()->create(['nombre' => 'M2.1 Fabricacion']);
    Concepto::factory()->create(['obra_id' => $this->obra->id, 'marca' => 'TG-CM2-2', 'activo' => true, 'cantidad' => 50]);

    $csv = encabezadoExport().renglonExport('55 Soldadura', 'M2.1 Fabricacion', 'TG-CM2-2', 1);

    subirExport($csv)->assertSessionHasErrors('csv_file');

    expect(Registro::count())->toBe(0);
});

test('reporta la ubicacion que trabajan varios grupos', function () {
    $ubicacion = Ubicacion::factory()->create(['nombre' => 'M2.4 Fabricacion']);
    GrupoTrabajo::factory()->create(['descripcion' => 'Cuadrilla A'])->ubicaciones()->attach($ubicacion);
    GrupoTrabajo::factory()->create(['descripcion' => 'Cuadrilla B'])->ubicaciones()->attach($ubicacion);
    Concepto::factory()->create(['obra_id' => $this->obra->id, 'marca' => 'PC-RPM1-1', 'activo' => true, 'cantidad' => 500]);

    $csv = encabezadoExport().renglonExport('55 Soldadura', 'M2.4 Fabricacion', 'PC-RPM1-1', 190);

    subirExport($csv)->assertSessionHasErrors('csv_file');

    expect(Registro::count())->toBe(0);
});

test('avisa cuando el archivo no trae movimientos del evento 55', function () {
    grupoConUbicacion('M3.6 Fabricacion');

    $csv = encabezadoExport().renglonExport('10 Corte', 'M3.6 Fabricacion', 'TG-CM5-1', 10);

    subirExport($csv)->assertSessionHasErrors('csv_file');

    expect(Registro::count())->toBe(0);
});

test('el formato manual de grupo y marca sigue funcionando', function () {
    GrupoTrabajo::factory()->create(['descripcion' => 'Grupo A']);
    Concepto::factory()->create(['obra_id' => $this->obra->id, 'marca' => 'V-01', 'activo' => true, 'cantidad' => 100]);

    $csv = "GRUPO,MARCA,CANTIDAD\nGrupo A,V-01,12\n";

    test()->actingAs($this->user)
        ->post(route('admin.prod.destajos.registros.import-csv', $this->destajo), [
            'csv_file' => UploadedFile::fake()->createWithContent('produccion.csv', $csv),
            'fecha' => '2026-02-05',
        ])
        ->assertSessionHasNoErrors();

    expect(Registro::sole()->cantidad)->toBe(12);
});

describe('marcas repetidas en varias etapas', function () {
    test('el export con etapa carga el renglon a la pieza correcta', function () {
        grupoConUbicacion('M3.6 Fabricacion');
        [$etapa1, $etapa2] = dosEtapasDeLaMismaMarca();

        $csv = encabezadoExportConEtapa()
            .renglonExportConEtapa('55 Soldadura', 'M3.6 Fabricacion', 'TG-CM5-1', '2', 7);

        subirExport($csv)->assertSessionHasNoErrors();

        expect(Registro::sole()->concepto_id)->toBe($etapa2->id)
            ->and(Registro::where('concepto_id', $etapa1->id)->count())->toBe(0);
    });

    test('el export con etapa suma los movimientos de la misma etapa y separa los de otra', function () {
        grupoConUbicacion('M3.6 Fabricacion');
        [$etapa1, $etapa2] = dosEtapasDeLaMismaMarca();

        $csv = encabezadoExportConEtapa()
            .renglonExportConEtapa('55 Soldadura', 'M3.6 Fabricacion', 'TG-CM5-1', '1', 3)
            .renglonExportConEtapa('55 Soldadura', 'M3.6 Fabricacion', 'TG-CM5-1', '1', 4)
            .renglonExportConEtapa('55 Soldadura', 'M3.6 Fabricacion', 'TG-CM5-1', '2', 5);

        subirExport($csv)->assertSessionHasNoErrors();

        expect(Registro::where('concepto_id', $etapa1->id)->sum('cantidad'))->toBe(7)
            ->and(Registro::where('concepto_id', $etapa2->id)->sum('cantidad'))->toBe(5);
    });

    test('sin columna etapa el renglon ambiguo se reporta en vez de cargarse', function () {
        grupoConUbicacion('M3.6 Fabricacion');
        dosEtapasDeLaMismaMarca();

        $csv = encabezadoExport().renglonExport('55 Soldadura', 'M3.6 Fabricacion', 'TG-CM5-1', 9);

        subirExport($csv)->assertSessionHasErrors('csv_file');

        expect(Registro::count())->toBe(0);
    });

    test('sin columna etapa una marca que no se repite sigue cargando', function () {
        grupoConUbicacion('M3.6 Fabricacion');
        $pieza = Concepto::factory()->create([
            'obra_id' => $this->obra->id,
            'marca' => 'TG-CM5-9',
            'etapa' => '3',
            'activo' => true,
            'cantidad' => 100,
        ]);

        $csv = encabezadoExport().renglonExport('55 Soldadura', 'M3.6 Fabricacion', 'TG-CM5-9', 9);

        subirExport($csv)->assertSessionHasNoErrors();

        expect(Registro::sole()->concepto_id)->toBe($pieza->id);
    });

    test('el formato manual acepta la columna etapa', function () {
        GrupoTrabajo::factory()->create(['descripcion' => 'Grupo A']);
        [, $etapa2] = dosEtapasDeLaMismaMarca();

        $csv = "GRUPO,MARCA,ETAPA,CANTIDAD\nGrupo A,TG-CM5-1,2,12\n";

        test()->actingAs($this->user)
            ->post(route('admin.prod.destajos.registros.import-csv', $this->destajo), [
                'csv_file' => UploadedFile::fake()->createWithContent('produccion.csv', $csv),
                'fecha' => '2026-02-05',
            ])
            ->assertSessionHasNoErrors();

        expect(Registro::sole()->concepto_id)->toBe($etapa2->id);
    });
});
