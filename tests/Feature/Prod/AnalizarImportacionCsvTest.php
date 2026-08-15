<?php

use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Pieza;
use App\Models\Prod\Registro;
use App\Models\Prod\Ubicacion;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * El import va en dos pasos. Este archivo cubre el primero: analizar el archivo
 * y devolver lo que pasaría, sin tocar la base. Lo que se enseña ahí es lo que
 * el usuario aprueba, así que tiene que coincidir con lo que después se escribe.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->destajo = Destajo::factory()->create([
        'fecha_inicio' => '2026-02-03',
        'fecha_fin' => '2026-02-09',
    ]);

    $this->soldadura = proceso();
    $this->marca = marcaConPiezas(0, ['marca' => 'TG-CM5-1']);
    obraPagaProcesos($this->marca->obra_id, $this->soldadura);

    $this->grupo = GrupoTrabajo::factory()->create(['descripcion' => 'Cuadrilla A']);
    $this->grupo->ubicaciones()->attach(Ubicacion::factory()->create(['nombre' => 'M3.6 Fabricacion']));

    $this->pieza = Pieza::factory()->create([
        'concepto_id' => $this->marca->id,
        'catalogo_id' => $this->marca->catalogo_id,
        'qr' => 'QR-500',
        'qs' => '12345',
    ]);
});

/**
 * @param  list<array{0: string, 1: string}>  $movimientos  pares de [proceso, ubicacion]
 */
function exportConMovimientos(array $movimientos, string $qs = '12345'): string
{
    $csv = "Proceso,Contracto,Movido el,Ubicacion,QS,Marca,Peso,Cantidad,Trabajador\n";

    foreach ($movimientos as [$proceso, $ubicacion]) {
        $csv .= "{$proceso},S26-05-05 REJAS,46195,{$ubicacion},{$qs},TG-CM5-1,1746,1,SAUL DZUL\n";
    }

    return $csv;
}

function analizar(string $csv, string $fecha = '2026-02-05')
{
    return test()->actingAs(test()->user)
        ->postJson(route('admin.prod.destajos.registros.analizar-csv', test()->destajo), [
            'csv_file' => UploadedFile::fake()->createWithContent('avance.csv', $csv),
            'fecha' => $fecha,
        ]);
}

test('analizar no escribe ningun registro', function () {
    analizar(exportConMovimientos([['75 Soldadura', 'M3.6 Fabricacion']]))
        ->assertOk()
        ->assertJsonPath('resumen.aplicables', 1);

    expect(Registro::count())->toBe(0);
});

test('analizar devuelve el QR al que se asigno cada renglon', function () {
    Pieza::factory()->create([
        'concepto_id' => $this->marca->id,
        'catalogo_id' => $this->marca->catalogo_id,
        'qr' => 'QR-100',
        'qs' => '12345',
    ]);

    analizar(exportConMovimientos([['75 Soldadura', 'M3.6 Fabricacion']]))
        ->assertOk()
        // QR-100 gana a QR-500 en orden natural.
        ->assertJsonPath('renglones.0.qr', 'QR-100')
        ->assertJsonPath('renglones.0.por_qs', true)
        ->assertJsonPath('renglones.0.candidatas', 2)
        ->assertJsonPath('resumen.asignadas_por_qs', 1);
});

test('analizar clasifica lo que no entra con su codigo', function () {
    analizar(exportConMovimientos([['75 Soldadura', 'Modulo Fantasma']]))
        ->assertOk()
        ->assertJsonPath('resumen.aplicables', 0)
        ->assertJsonPath('resumen.errores', 1)
        ->assertJsonPath('renglones.0.codigo', 'ubicacion_desconocida');
});

test('analizar agrega los eventos que no pagan destajo en vez de listarlos', function () {
    $csv = exportConMovimientos([
        ['75 Soldadura', 'M3.6 Fabricacion'],
        ['10 Corte', 'M3.6 Fabricacion'],
        ['10 Corte', 'M3.6 Fabricacion'],
    ]);

    $respuesta = analizar($csv)->assertOk();

    // Los eventos que no pagan son la mayoria del export: van agregados.
    expect($respuesta->json('renglones'))->toHaveCount(1)
        ->and($respuesta->json('ignorados'))->toHaveCount(1)
        ->and($respuesta->json('ignorados.0.evento'))->toBe('10')
        ->and($respuesta->json('ignorados.0.renglones'))->toBe(2)
        ->and($respuesta->json('resumen.ignorados_por_evento'))->toBe(2);
});

test('analizar corta por grupo lo que entra y lo que no', function () {
    $otro = GrupoTrabajo::factory()->create(['descripcion' => 'Cuadrilla B']);
    $otro->ubicaciones()->attach(Ubicacion::factory()->create(['nombre' => 'M4.1 Pintura']));

    Pieza::factory()->create([
        'concepto_id' => $this->marca->id,
        'catalogo_id' => $this->marca->catalogo_id,
        'qr' => 'QR-600',
        'qs' => '67890',
    ]);

    $csv = exportConMovimientos([['75 Soldadura', 'M3.6 Fabricacion']])
        // Otra cuadrilla, otra pieza: tiene que salir en su propio renglon.
        ."75 Soldadura,S26-05-05 REJAS,46195,M4.1 Pintura,67890,TG-CM5-1,1746,1,SAUL DZUL\n"
        // Sin ubicacion en el catalogo no hay grupo a quien cargarle esto.
        ."75 Soldadura,S26-05-05 REJAS,46195,Modulo Fantasma,12345,TG-CM5-1,1746,1,SAUL DZUL\n";

    $grupos = analizar($csv)->assertOk()->json('por_grupo');

    expect($grupos)->toHaveCount(3)
        ->and($grupos[0])->toMatchArray(['grupo' => 'Cuadrilla A', 'movimientos' => 1, 'piezas' => 1.0, 'no_entran' => 0])
        ->and($grupos[1])->toMatchArray(['grupo' => 'Cuadrilla B', 'movimientos' => 1, 'piezas' => 1.0, 'no_entran' => 0])
        // Lo que no resolvio grupo va hasta abajo: es trabajo pendiente sobre el archivo.
        ->and($grupos[2])->toMatchArray(['grupo' => null, 'movimientos' => 0, 'no_entran' => 1]);
});

test('el corte por grupo cuenta piezas equivalentes, no movimientos', function () {
    $csv = "GRUPO,QS,PROCESO,PORCENTAJE\n"
        ."Cuadrilla A,12345,Soldadura,60\n";

    $grupos = analizar($csv)->assertOk()->json('por_grupo');

    // Un movimiento al 60% se escribe una vez pero se paga como 0.6 de pieza.
    expect($grupos[0])->toMatchArray(['grupo' => 'Cuadrilla A', 'movimientos' => 1, 'piezas' => 0.6]);
});

test('el corte por grupo suma el archivo completo aunque el detalle vaya truncado', function () {
    $movimientos = [];
    $piezas = [];

    // Una pieza por movimiento, o el tope los iria rechazando. Se insertan de
    // golpe: con factory, mil doscientas piezas hacen el test eterno.
    for ($i = 0; $i < 1200; $i++) {
        $movimientos[] = ['75 Soldadura', 'M3.6 Fabricacion'];
        $piezas[] = [
            'concepto_id' => $this->marca->id,
            'catalogo_id' => $this->marca->catalogo_id,
            'qr' => "QR-MASIVO-{$i}",
            'qs' => '12345',
            'activo' => true,
        ];
    }

    Pieza::insert($piezas);

    $respuesta = analizar(exportConMovimientos($movimientos))->assertOk();

    expect($respuesta->json('truncado'))->toBeTrue()
        ->and($respuesta->json('renglones'))->toHaveCount(1000)
        // Si el corte se calculara en el front, sobre lo truncado, dirian 1000.
        ->and($respuesta->json('por_grupo.0.movimientos'))->toBe($respuesta->json('resumen.aplicables'))
        ->and($respuesta->json('por_grupo.0.movimientos'))->toBeGreaterThan(1000);
});

test('analizar rechaza el destajo cerrado y la fecha fuera del periodo', function () {
    analizar(exportConMovimientos([['75 Soldadura', 'M3.6 Fabricacion']]), '2026-03-01')
        ->assertStatus(422)
        ->assertJsonPath('errors.fecha.0', 'La fecha debe estar dentro del periodo del destajo.');

    $this->destajo->update(['cerrado' => true]);

    analizar(exportConMovimientos([['75 Soldadura', 'M3.6 Fabricacion']]))
        ->assertStatus(422)
        ->assertJsonPath('errors.error.0', 'No se puede importar produccion en un destajo cerrado.');

    expect(Registro::count())->toBe(0);
});

test('confirmar escribe exactamente lo que el analisis prometio', function () {
    $csv = exportConMovimientos([
        ['75 Soldadura', 'M3.6 Fabricacion'],
        ['10 Corte', 'M3.6 Fabricacion'],
    ]);

    $prometidos = analizar($csv)->assertOk()->json('resumen.aplicables');

    $this->actingAs($this->user)
        ->post(route('admin.prod.destajos.registros.import-csv', $this->destajo), [
            'csv_file' => UploadedFile::fake()->createWithContent('avance.csv', $csv),
            'fecha' => '2026-02-05',
        ])
        ->assertSessionHasNoErrors();

    expect(Registro::count())->toBe($prometidos);
});
