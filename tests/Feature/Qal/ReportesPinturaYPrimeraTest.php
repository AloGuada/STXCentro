<?php

use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\NivelAql;
use App\Enums\Qal\SubtipoPrimera;
use App\Enums\Qal\VeredictoLote;
use App\Models\Concepto;
use App\Models\Prod\Pieza;
use App\Models\Qal\Adherencia;
use App\Models\Qal\Defecto;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Pintura;
use App\Models\Qal\PuntoInspeccion;
use App\Services\Qal\Formatos\ControlPintura;
use App\Services\Qal\Formatos\Espesores;
use App\Services\Qal\Formatos\FiltrosDeReporte;
use App\Services\Qal\Formatos\PrimeraTransformacion;
use App\Services\Qal\Formatos\PruebaDeAdherencia;
use App\Services\Qal\Formatos\VisualPintura;
use Database\Seeders\QalPuntosInspeccionSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * Los formatos PDF de pintura y de 1ª: lo distintivo de cada hoja, sobre sus
 * `datos()`. El PDF de cada uno lo cubre ReportesTest.
 */
beforeEach(fn () => $this->seed(QalPuntosInspeccionSeeder::class));

function inspeccionDePintura(Pieza $pieza, array $datos = []): Inspeccion
{
    return Inspeccion::factory()->dePieza($pieza, FaseTransformacion::Tercera)->create($datos);
}

function contestarPunto(Inspeccion $inspeccion, string $clave, string $resultado): void
{
    $inspeccion->puntos()->create([
        'punto_id' => PuntoInspeccion::query()->where('clave', $clave)->value('id'),
        'resultado' => $resultado,
        'valor_texto' => $resultado,
    ]);
}

/**
 * @param  array<int, list<float>>  $lecturas  medición ⇒ lecturas del calibre
 */
function medirEspesores(Inspeccion $inspeccion, float $requerido, array $lecturas, ?bool $cumple = true): Pintura
{
    $pintura = Pintura::create(['inspeccion_id' => $inspeccion->id, 'espesor_requerido_mils' => $requerido, 'mediciones_visibles' => 5, 'cumple' => $cumple]);

    foreach ($lecturas as $medicion => $valores) {
        foreach ($valores as $i => $valor) {
            $pintura->lecturas()->create(['medicion' => $medicion, 'lectura' => $i + 1, 'valor_mils' => $valor]);
        }
    }

    return $pintura;
}

function defectoDePintura(Inspeccion $inspeccion, string $nombre): void
{
    $defecto = Defecto::query()->firstOrCreate(['ambito' => 'pintura', 'nombre' => $nombre], ['activo' => true]);
    $inspeccion->defectos()->create(['defecto_id' => $defecto->id, 'cantidad' => 1]);
}

test('espesores promedia las lecturas de cada medicion y marca las que quedan bajo el 80 por ciento', function () {
    $marca = Concepto::factory()->create(['marca' => 'SX-TP1-1']);
    $medida = inspeccionDePintura(Pieza::factory()->create(['concepto_id' => $marca->id, 'qs' => '4']));
    medirEspesores($medida, 5, [1 => [5, 6, 7], 2 => [3, 3, 3], 3 => [6, 6, 6], 4 => [6, 6, 6], 5 => [6, 6, 6], 6 => [6]]);
    // Inspeccionada sin medir: no tiene nada que decir en esta hoja.
    inspeccionDePintura(Pieza::factory()->create(['concepto_id' => $marca->id, 'qs' => '5']));

    $datos = app(Espesores::class)->datos(new FiltrosDeReporte(obraId: $marca->obra_id, estatus: 'liberadas', vista: 'final'));
    $fila = $datos['renglones'][0];

    expect($datos['renglones'])->toHaveCount(1)
        ->and($datos['columnas'])->toBe(6)
        ->and($fila['pieza'])->toBe('SX-TP1-1 #4')
        ->and($fila['mediciones'][0])->toBe(['texto' => '6', 'bajo' => false])
        ->and($fila['mediciones'][1])->toBe(['texto' => '3', 'bajo' => true])
        ->and($fila['promedio'])->toBe('5.5')
        ->and($fila['requerido'])->toBe('5')
        ->and($fila['resultado']['texto'])->toBe('Aceptado');
});

test('la visual de pintura limpia la pieza liberada y la prueba de adherencia manda sobre la visual', function () {
    $marca = Concepto::factory()->create();
    $liberada = inspeccionDePintura(Pieza::factory()->create(['concepto_id' => $marca->id, 'qs' => '1']), ['estatus' => EstatusInspeccion::Liberado]);
    contestarPunto($liberada, 'p3_vis', 'no_ok');
    defectoDePintura($liberada, 'Falta pintura (FP)');
    medirEspesores($liberada, 4, [1 => [5, 5, 5]]);

    $rechazada = inspeccionDePintura(Pieza::factory()->create(['concepto_id' => $marca->id, 'qs' => '2']), ['estatus' => EstatusInspeccion::Rechazado]);
    contestarPunto($rechazada, 'p3_adh', 'ok');
    Adherencia::create(['inspeccion_id' => $rechazada->id, 'metodo' => 'B', 'resultado' => 'Rechazado']);
    defectoDePintura($rechazada, 'Falta pintura (FP)');
    defectoDePintura($rechazada, 'Burbujas');

    $datos = app(VisualPintura::class)->datos(new FiltrosDeReporte(obraId: $marca->obra_id, estatus: 'todas', vista: 'final'));
    [$primera, $segunda] = $datos['renglones'];
    $columna = fn (array $fila, string $defecto): string => $fila['defectos'][array_search($defecto, $datos['defectos'], true)]['texto'];

    expect($primera['criterios'][1]['texto'])->toBe('A')
        ->and(array_unique(array_column($primera['defectos'], 'texto')))->toBe([''])
        ->and($primera['promedio'])->toBe('5')
        ->and($primera['observaciones'])->toBe('N/A')
        ->and($segunda['criterios'][2]['texto'])->toBe('D')
        ->and($columna($segunda, 'FP'))->toBe('✗')
        // Un defecto sin columna propia no se pierde: cae en «Otro».
        ->and($columna($segunda, 'Otro'))->toBe('✗')
        ->and($columna($segunda, 'EB'))->toBe('')
        ->and($segunda['promedio'])->toBeNull()
        ->and($datos['aviso'])->toStartWith('1 de 2 pieza(s) no tienen medición de espesor');
});

test('la prueba de adherencia pagina sus evidencias de seis en seis y nombra los pdf', function () {
    Storage::fake('public');
    $marca = Concepto::factory()->create(['marca' => 'SX-CM1-1']);
    $inspeccion = inspeccionDePintura(Pieza::factory()->create(['concepto_id' => $marca->id, 'qs' => '1']));
    $prueba = Adherencia::create(['inspeccion_id' => $inspeccion->id, 'metodo' => 'A', 'resultado' => 'Aceptado']);
    $prueba->tiras()->createMany([['orden' => 1, 'clasificacion' => '5A'], ['orden' => 2, 'clasificacion' => '4A']]);

    for ($i = 1; $i <= 6; $i++) {
        Storage::disk('public')->put("qal/adherencia/{$i}.jpg", 'jpg');
        $prueba->fotos()->create(['path' => "qal/adherencia/{$i}.jpg", 'nombre_original' => "tira-{$i}.jpg", 'mime' => 'image/jpeg']);
    }

    $prueba->fotos()->create(['path' => 'qal/adherencia/acta.pdf', 'nombre_original' => 'acta.pdf', 'mime' => 'application/pdf']);

    $datos = app(PruebaDeAdherencia::class)->datos(new FiltrosDeReporte(obraId: $marca->obra_id, estatus: 'liberadas', vista: 'final'));

    expect($datos['renglones'][0])->toMatchArray(['pieza' => 'SX-CM1-1 #1', 'metodo' => 'A (en cruz)', 'tiras' => ['5A', '4A', '']])
        ->and($datos['renglones'][0]['resultado']['texto'])->toBe('Aceptado')
        ->and($datos['evidencias'])->toHaveCount(2)
        ->and($datos['evidencias'][0]['marcos'][0]['rotulo'])->toBe('SX-CM1-1 #1 (1/7)')
        ->and($datos['evidencias'][0]['marcos'][0]['ruta'])->toBe(Storage::disk('public')->path('qal/adherencia/1.jpg'))
        ->and($datos['evidencias'][1]['marcos'][0])->toMatchArray(['ruta' => null, 'pdf' => 'acta.pdf'])
        ->and($datos['evidencias'][1]['marcos'][1])->toBeNull();
});

test('el control diario trae cada revision de la pieza y suma solo los kilos liberados', function () {
    $marca = Concepto::factory()->create();
    $pieza = Pieza::factory()->create(['concepto_id' => $marca->id, 'qs' => '1']);
    $primera = inspeccionDePintura($pieza, ['estatus' => EstatusInspeccion::Rechazado, 'kg' => 100]);
    contestarPunto($primera, 'p3_esp', 'no_ok');
    $segunda = inspeccionDePintura($pieza, ['estatus' => EstatusInspeccion::Liberado, 'numero_inspeccion' => 2, 'kg' => 100]);
    contestarPunto($segunda, 'p3_esp', 'ok');
    $otra = inspeccionDePintura(Pieza::factory()->create(['concepto_id' => $marca->id, 'qs' => '2']), ['estatus' => EstatusInspeccion::Rechazado, 'kg' => 50]);
    contestarPunto($otra, 'p3_esp', 'no_ok');

    // Pida la vista que pida, el control diario lee la pieza como quedó.
    $datos = app(ControlPintura::class)->datos(new FiltrosDeReporte(obraId: $marca->obra_id, vista: 'historico'));
    [$liberada, $rechazada] = $datos['renglones'];

    expect($datos['renglones'])->toHaveCount(2)
        ->and(array_column($liberada['revisiones'], 'texto'))->toBe(['✗', '✓', ''])
        ->and($liberada['pruebas'][0]['texto'])->toBe('✓')
        ->and($rechazada['rechazo'])->toBe('EB')
        ->and($datos['kg_liberados'])->toBe(100.0);
});

test('la de primera trae los criterios, los empates y el muestreo del lote', function () {
    $marca = Concepto::factory()->create(['marca' => 'SX-PL1-1']);
    $inspeccion = Inspeccion::factory()->deMarca($marca, 3)->create([
        'subtipo' => SubtipoPrimera::Placa, 'cantidad_lote' => 40, 'estatus' => EstatusInspeccion::Rechazado,
    ]);
    contestarPunto($inspeccion, 'p1_corte', 'no_ok');
    $inspeccion->puntos()->create(['punto_id' => PuntoInspeccion::query()->where('clave', 'p1_empates')->value('id'), 'valor_numerico' => 2]);
    $inspeccion->muestreo()->create([
        'tamano_lote' => 40, 'nivel' => NivelAql::cases()[0], 'muestra' => 8, 'aceptacion' => 1, 'rechazo' => 2,
        'conformes' => 6, 'rechazadas' => 2, 'veredicto' => VeredictoLote::Rechazado,
    ]);

    $datos = app(PrimeraTransformacion::class)->datos(new FiltrosDeReporte(obraId: $marca->obra_id));
    $fila = $datos['renglones'][0];
    $criterio = fn (string $nombre): string => $fila['criterios'][array_search($nombre, $datos['criterios'], true)]['texto'];

    expect($fila['marca'])->toBe('SX-PL1-1 #3')
        ->and($fila['tipo'])->toBe(SubtipoPrimera::Placa->etiqueta())
        ->and($fila['cantidad'])->toBe(40)
        ->and($criterio('Defectos de corte'))->toBe('D')
        ->and($criterio('Empates / juntas'))->toBe('2')
        ->and($criterio('Longitud'))->toBe('—')
        ->and($fila['muestreo'])->toMatchArray(['lote' => 40, 'muestra' => 8, 'rechazadas' => 2])
        ->and($fila['muestreo']['veredicto']['texto'])->toBe('Rechazado')
        ->and($datos['totales'])->toMatchArray(['piezas' => 40, 'registros' => 1, 'rechazadas' => 1]);
});
