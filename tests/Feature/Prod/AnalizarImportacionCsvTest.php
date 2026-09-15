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
        ->assertJsonPath('resumen.asignadas_por_sistema', 1);
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

/**
 * El export de planta que no numera pieza: mismas columnas, sin QS ni QR. Antes
 * de esto reventaba con "Undefined array key QS" y se caia el import entero.
 *
 * @param  list<array{0: string, 1: string, 2: string}>  $movimientos  ternas de [proceso, ubicacion, marca]
 */
function exportSinQs(array $movimientos): string
{
    $csv = "Proceso,Contracto,Movido el,Ubicacion,Marca,Peso,Cantidad,Trabajador\n";

    foreach ($movimientos as [$proceso, $ubicacion, $marca]) {
        $csv .= "{$proceso},S26-05-05 REJAS,46195,{$ubicacion},{$marca},1746,1,SAUL DZUL\n";
    }

    return $csv;
}

/**
 * El export de planta con QR: el QR es de la orden de trabajo y puede ser uno
 * que el catálogo ya reemplazó.
 *
 * @param  list<array{0: string, 1: string, 2: string, 3: string}>  $movimientos  [proceso, ubicacion, qr, marca]
 */
function exportConQr(array $movimientos): string
{
    $csv = "Proceso,Contracto,Movido el,Ubicacion,QR,Marca,Peso,Cantidad,Trabajador\n";

    foreach ($movimientos as [$proceso, $ubicacion, $qr, $marca]) {
        $csv .= "{$proceso},S26-05-05 REJAS,46195,{$ubicacion},{$qr},{$marca},1746,1,SAUL DZUL\n";
    }

    return $csv;
}

test('un QR que ya no esta en el catalogo cuenta contra su marca', function () {
    // La orden vieja tenia QR-OLD; el catalogo se recargo y ese QR se apago.
    Pieza::factory()->create([
        'concepto_id' => $this->marca->id,
        'catalogo_id' => $this->marca->catalogo_id,
        'qr' => 'QR-OLD',
        'activo' => false,
    ]);

    $respuesta = analizar(exportConQr([['75 Soldadura', 'M3.6 Fabricacion', 'QR-OLD', 'TG-CM5-1']]))->assertOk();

    expect($respuesta->json('resumen.aplicables'))->toBe(1)
        ->and($respuesta->json('renglones.0.qr'))->toBe('QR-500')
        ->and($respuesta->json('renglones.0.asignado_por'))->toBe('marca')
        ->and($respuesta->json('renglones.0.por_qs'))->toBeTrue();
});

test('un QR desconocido sin marca sigue siendo error', function () {
    $respuesta = analizar(exportConQr([['75 Soldadura', 'M3.6 Fabricacion', 'QR-NADIE', '']]))->assertOk();

    expect($respuesta->json('renglones.0.estado'))->toBe('error')
        ->and($respuesta->json('renglones.0.codigo'))->toBe('pieza_no_encontrada');
});

test('dos QR del mismo modelo no rebasan juntos la cantidad del modelo', function () {
    // El modelo pide 1 pieza pero la orden trae dos QR activos.
    $this->marca->update(['cantidad' => 1]);
    Pieza::factory()->create([
        'concepto_id' => $this->marca->id,
        'catalogo_id' => $this->marca->catalogo_id,
        'qr' => 'QR-501',
        'qs' => null,
    ]);

    $respuesta = analizar(exportConQr([
        ['75 Soldadura', 'M3.6 Fabricacion', 'QR-500', 'TG-CM5-1'],
        ['75 Soldadura', 'M3.6 Fabricacion', 'QR-501', 'TG-CM5-1'],
    ]))->assertOk();

    $rechazado = collect($respuesta->json('renglones'))->firstWhere('estado', 'error');

    expect($respuesta->json('resumen.aplicables'))->toBe(1)
        ->and($rechazado['qr'])->toBe('QR-501')
        ->and($rechazado['codigo'])->toBe('sin_tope')
        ->and($rechazado['motivo'])->toContain('ya tiene pagadas sus 1 piezas');
});

test('el export sin columna QS ya no revienta', function () {
    analizar(exportSinQs([['75 Soldadura', 'M3.6 Fabricacion', 'TG-CM5-1']]))
        ->assertOk()
        ->assertJsonPath('resumen.aplicables', 1);
});

test('cinco renglones de la misma marca toman los cinco QR mas chicos sin pagar', function () {
    // La pieza del beforeEach es QR-500; estas quedan por debajo y por encima
    // para que el orden natural importe: 7 piezas para 5 movimientos.
    foreach (['QR-9', 'QR-10', 'QR-100', 'QR-3', 'QR-70', 'QR-800'] as $qr) {
        Pieza::factory()->create([
            'concepto_id' => $this->marca->id,
            'catalogo_id' => $this->marca->catalogo_id,
            'qr' => $qr,
            'qs' => null,
        ]);
    }

    $movimientos = array_fill(0, 5, ['75 Soldadura', 'M3.6 Fabricacion', 'TG-CM5-1']);

    $respuesta = analizar(exportSinQs($movimientos))->assertOk();

    expect($respuesta->json('resumen.aplicables'))->toBe(5)
        // Orden natural, no alfabetico: QR-10 va despues de QR-9, no antes.
        ->and(array_column($respuesta->json('renglones'), 'qr'))
        ->toBe(['QR-3', 'QR-9', 'QR-10', 'QR-70', 'QR-100'])
        ->and($respuesta->json('resumen.asignadas_por_sistema'))->toBe(5)
        ->and($respuesta->json('renglones.0.asignado_por'))->toBe('marca');
});

test('lo ya pagado no se vuelve a tomar', function () {
    Pieza::factory()->create([
        'concepto_id' => $this->marca->id,
        'catalogo_id' => $this->marca->catalogo_id,
        'qr' => 'QR-1',
        'qs' => null,
    ]);

    // QR-1 es el mas chico, pero ya se capturo completo en soldadura.
    Registro::factory()->create([
        'fecha' => '2026-02-04',
        'pieza_id' => Pieza::where('qr', 'QR-1')->value('id'),
        'proceso_id' => $this->soldadura->id,
        'grupo_trabajo_id' => $this->grupo->id,
        'porcentaje' => 100,
    ]);

    $respuesta = analizar(exportSinQs([['75 Soldadura', 'M3.6 Fabricacion', 'TG-CM5-1']]))->assertOk();

    expect($respuesta->json('renglones.0.qr'))->toBe('QR-500')
        ->and($respuesta->json('resumen.aplicables'))->toBe(1);
});

test('mas renglones que piezas sin pagar: el sobrante no entra', function () {
    // Solo existe la pieza del beforeEach, y el archivo pide tres.
    $movimientos = array_fill(0, 3, ['75 Soldadura', 'M3.6 Fabricacion', 'TG-CM5-1']);

    $respuesta = analizar(exportSinQs($movimientos))->assertOk();

    expect($respuesta->json('resumen.aplicables'))->toBe(1)
        ->and($respuesta->json('resumen.omitidas'))->toBe(2)
        ->and($respuesta->json('renglones.1.codigo'))->toBe('duplicado');
});

test('la marca que no esta en ningun catalogo vigente se reporta con su nombre', function () {
    $respuesta = analizar(exportSinQs([['75 Soldadura', 'M3.6 Fabricacion', 'NO-EXISTE-1']]))->assertOk();

    expect($respuesta->json('resumen.errores'))->toBe(1)
        ->and($respuesta->json('renglones.0.codigo'))->toBe('pieza_no_encontrada')
        // Sin pieza resuelta, la marca del archivo es lo unico que identifica el renglon.
        ->and($respuesta->json('renglones.0.marca'))->toBe('NO-EXISTE-1');
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

test('confirmar un archivo sin QS escribe una pieza distinta por renglon', function () {
    foreach (['QR-2', 'QR-3'] as $qr) {
        Pieza::factory()->create([
            'concepto_id' => $this->marca->id,
            'catalogo_id' => $this->marca->catalogo_id,
            'qr' => $qr,
            'qs' => null,
        ]);
    }

    $csv = exportSinQs(array_fill(0, 3, ['75 Soldadura', 'M3.6 Fabricacion', 'TG-CM5-1']));

    $this->actingAs($this->user)
        ->post(route('admin.prod.destajos.registros.import-csv', $this->destajo), [
            'csv_file' => UploadedFile::fake()->createWithContent('avance.csv', $csv),
            'fecha' => '2026-02-05',
        ])
        ->assertSessionHasNoErrors();

    // Tres renglones, tres piezas: nunca se paga dos veces la misma.
    expect(Registro::count())->toBe(3)
        ->and(Registro::distinct()->count('pieza_id'))->toBe(3);
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
