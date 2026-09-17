<?php

use App\Enums\Qal\AmbitoDefecto;
use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\ResultadoJunta;
use App\Enums\Qal\Subetapa;
use App\Enums\Qal\TipoDatoPunto;
use App\Models\Concepto;
use App\Models\Obra as ObraDelPortal;
use App\Models\Prod\Pieza;
use App\Models\Qal\Defecto;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Junta;
use App\Models\Qal\PuntoInspeccion;
use App\Models\Qal\Soldador;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

/**
 * El tablero de Calidad, calculado de `qal_inspecciones`.
 *
 * Lo que se prueba son las definiciones, que son las que se discutieron en el
 * tablero anterior: la unidad es la pieza y no la inspección, el FPY mira sólo
 * la primera inspección, en armado «pendiente» es veredicto, las tasas llevan su
 * cobertura y los grupos con pocas piezas no se publican.
 */
function usuarioDelTablero(): User
{
    Permission::firstOrCreate(['name' => 'qal.dashboard.ver', 'guard_name' => 'web']);

    $usuario = User::factory()->create();
    $usuario->givePermissionTo('qal.dashboard.ver');

    return $usuario;
}

function piezaDelTablero(ObraDelPortal $obra): Pieza
{
    return Pieza::factory()->create(['concepto_id' => Concepto::factory()->create(['obra_id' => $obra->id])->id]);
}

/**
 * @param  array<string, mixed>  $datos
 */
function inspeccionDelTablero(Pieza $pieza, FaseTransformacion $fase, EstatusInspeccion $estatus, array $datos = []): Inspeccion
{
    $subetapa = $datos['subetapa'] ?? ($fase === FaseTransformacion::Segunda ? Subetapa::Soldado : null);
    unset($datos['subetapa']);

    return Inspeccion::factory()->dePieza($pieza, $fase, $subetapa)->create(['estatus' => $estatus] + $datos);
}

/**
 * @return array<string, mixed>
 */
function propsDelTablero(mixed $test, array $filtros = []): array
{
    $props = [];

    $test->actingAs(usuarioDelTablero())
        ->get(route('admin.qal.dashboard', $filtros))
        ->assertOk()
        ->assertInertia(function ($page) use (&$props) {
            $page->component('admin/calidad/dashboard/index');
            $props = $page->toArray()['props'];
        });

    return $props;
}

test('el rechazo se cuenta por pieza y el FPY sólo con la primera inspección', function () {
    $obra = ObraDelPortal::factory()->create(['no' => 'T4 CANCUN']);

    // Rechazada y liberada al segundo intento: una pieza con rechazo, no dos.
    $retrabajada = piezaDelTablero($obra);
    inspeccionDelTablero($retrabajada, FaseTransformacion::Segunda, EstatusInspeccion::Rechazado, ['fecha' => now()->subDays(3)]);
    inspeccionDelTablero($retrabajada, FaseTransformacion::Segunda, EstatusInspeccion::Liberado, ['numero_inspeccion' => 2]);

    // Limpia en 2ª y después pintada: la única que pasó por las dos etapas.
    $limpia = piezaDelTablero($obra);
    inspeccionDelTablero($limpia, FaseTransformacion::Segunda, EstatusInspeccion::Liberado);
    inspeccionDelTablero($limpia, FaseTransformacion::Tercera, EstatusInspeccion::Liberado);

    $props = propsDelTablero($this);
    $resumen = $props['tablero']['resumen'];

    expect($resumen['rechazo']['2ª'])->toMatchArray(['pct' => 50, 'conRechazo' => 1, 'piezas' => 2, 'reprocesos' => 1])
        ->and($resumen['rechazo']['3ª'])->toMatchArray(['pct' => 0, 'piezas' => 1])
        // Por inspección es otra cosa: una rechazada de cuatro con veredicto.
        ->and($resumen['inspeccionesRechazadas'])->toEqual(25)
        ->and($resumen['fpy']['2ª'])->toEqual(['pct' => 50, 'n' => 2])
        // Ninguna acabó rechazada: la retrabajada se liberó.
        ->and($resumen['rechazoFinal']['2ª'])->toEqual(['pct' => 0, 'n' => 2])
        ->and($resumen['liberadas'])->toEqual(['piezas' => 3, 'unidades' => 3, 'obras' => 1])
        ->and($resumen['pendientes'])->toBe(0)
        ->and($resumen['enAmbas'])->toBe(1)
        ->and($props['tablero']['operacion']['rechazoPorFase'])->toEqual([
            ['fase' => '2ª', 'pct' => 50, 'n' => 2],
            ['fase' => '3ª', 'pct' => 0, 'n' => 1],
        ])
        ->and($props['tablero']['operacion']['resultadoPorObra'])->toEqual([
            ['obra' => 'T4 CANCUN', 'liberadas' => 3, 'rechazadas' => 0, 'pendientes' => 0],
        ]);
});

test('en armado pendiente es veredicto y en las demas etapas es trabajo sin cerrar', function () {
    $obra = ObraDelPortal::factory()->create();

    inspeccionDelTablero(piezaDelTablero($obra), FaseTransformacion::Segunda, EstatusInspeccion::Pendiente, ['subetapa' => Subetapa::ArmadoVestido]);
    inspeccionDelTablero(piezaDelTablero($obra), FaseTransformacion::Tercera, EstatusInspeccion::Pendiente, ['fecha' => now()->subDays(20)]);

    $resumen = propsDelTablero($this)['tablero']['resumen'];

    expect($resumen['pendientes'])->toBe(1)
        ->and($resumen['liberadas']['piezas'])->toBe(1)
        ->and($resumen['rechazo']['2ª']['pct'])->toEqual(0)
        ->and($resumen['rechazo']['3ª'])->toBeNull()
        // Lleva tres semanas sin veredicto: es alerta.
        ->and($resumen['alertas']['pendientesViejas'])->toBe(1);
});

test('los filtros acotan el calculo pero no las listas de la barra', function () {
    $cancun = ObraDelPortal::factory()->create(['no' => 'CANCUN']);
    $parks = ObraDelPortal::factory()->create(['no' => 'PARKS']);

    inspeccionDelTablero(piezaDelTablero($cancun), FaseTransformacion::Segunda, EstatusInspeccion::Rechazado);
    inspeccionDelTablero(piezaDelTablero($parks), FaseTransformacion::Segunda, EstatusInspeccion::Liberado);

    $props = propsDelTablero($this, ['obra' => $parks->id, 'fase' => '2ª']);

    expect($props['filtros'])->toMatchArray(['obra' => (string) $parks->id, 'fase' => '2ª', 'soldador' => null])
        ->and($props['tablero']['resumen']['inspecciones']['total'])->toBe(1)
        ->and($props['tablero']['resumen']['rechazo']['2ª']['pct'])->toEqual(0)
        ->and(collect($props['opciones']['obras'])->pluck('texto')->all())->toBe(['CANCUN', 'PARKS']);
});

test('rechazo por agrupa por la primera inspeccion y calla los grupos con pocas piezas', function () {
    $obra = ObraDelPortal::factory()->create(['no' => 'STEELEX 2']);
    $soldador = Soldador::factory()->create(['clave' => 'MHV']);

    // Cinco piezas: basta para una obra (4) y no para juzgar a una persona (10).
    foreach (range(1, 5) as $n) {
        $pieza = piezaDelTablero($obra);
        inspeccionDelTablero($pieza, FaseTransformacion::Segunda, $n === 1 ? EstatusInspeccion::Rechazado : EstatusInspeccion::Liberado, [
            'soldador_id' => $soldador->id,
            'fecha' => now()->subDay(),
        ]);
    }
    // El retrabajo lo hizo otro soldador: la pieza sigue contando con MHV.
    $otro = Soldador::factory()->create();
    inspeccionDelTablero(Pieza::query()->first(), FaseTransformacion::Segunda, EstatusInspeccion::Liberado, [
        'soldador_id' => $otro->id,
        'numero_inspeccion' => 2,
    ]);

    $rechazoPor = propsDelTablero($this)['tablero']['operacion']['rechazoPor'];

    expect($rechazoPor['obra']['filas'])->toEqual([['nombre' => 'STEELEX 2', 'pct' => 20, 'n' => 5, 'fase' => '2ª']])
        ->and($rechazoPor['soldador']['filas'])->toBe([])
        ->and($rechazoPor['soldador'])->toMatchArray(['minimo' => 10, 'fuera' => 1, 'total' => 5]);
});

test('la tasa por elemento cuenta la exposicion una vez por pieza y dice su cobertura', function () {
    $obra = ObraDelPortal::factory()->create(['no' => 'T4']);
    $elementos = PuntoInspeccion::factory()->create([
        'clave' => 'p2_elem',
        'fase' => FaseTransformacion::Segunda,
        'subetapa' => Subetapa::Soldado,
        'tipo_dato' => TipoDatoPunto::Numero,
        'opciones' => null,
    ]);
    $socavado = Defecto::factory()->create(['nombre' => 'Socavado']);
    $poro = Defecto::factory()->create(['nombre' => 'Porosidad']);

    $medida = inspeccionDelTablero(piezaDelTablero($obra), FaseTransformacion::Segunda, EstatusInspeccion::Rechazado);
    $medida->puntos()->create(['punto_id' => $elementos->id, 'valor_numerico' => 10]);
    $medida->defectos()->create(['defecto_id' => $socavado->id, 'cantidad' => 2]);
    $medida->defectos()->create(['defecto_id' => $poro->id, 'cantidad' => 1]);

    // Sin número de elementos: entra al total de piezas, no a la tasa.
    $sinMedida = inspeccionDelTablero(piezaDelTablero($obra), FaseTransformacion::Segunda, EstatusInspeccion::Rechazado);
    $sinMedida->defectos()->create(['defecto_id' => $socavado->id, 'cantidad' => 5]);

    $tablero = propsDelTablero($this)['tablero'];

    expect($tablero['tasas']['elem'])->toMatchArray([
        'tasa' => 0.3,
        'defectos' => 3,
        'exposicion' => 10,
        'cobertura' => ['pct' => 50, 'con' => 1, 'total' => 2, 'fuera' => 0],
    ])
        ->and($tablero['tasas']['elem']['porDimension']['obra'])->toEqual([['nombre' => 'T4', 'tasa' => 0.3, 'exposicion' => 10]])
        // Sin pintura no hay base de m²: ni tasa ni cobertura que publicar.
        ->and($tablero['tasas']['m2']['tasa'])->toBeNull()
        ->and($tablero['operacion']['pareto']['p2_deftypes'])->toEqual([
            ['causa' => 'Socavado', 'n' => 7],
            ['causa' => 'Porosidad', 'n' => 1],
        ]);
});

test('el pareto de armado cuenta los puntos que no cumplen y los contadores por su numero', function () {
    $obra = ObraDelPortal::factory()->create();
    $faltaVestido = PuntoInspeccion::factory()->create([
        'clave' => 'p2_faltavest',
        'etiqueta' => 'Falta de vestido',
        'fase' => FaseTransformacion::Segunda,
        'subetapa' => Subetapa::ArmadoVestido,
        'tipo_dato' => TipoDatoPunto::Contador,
        'opciones' => null,
    ]);
    $bisel = PuntoInspeccion::factory()->create(['etiqueta' => 'Ángulo de bisel', 'fase' => FaseTransformacion::Segunda]);

    $armado = inspeccionDelTablero(piezaDelTablero($obra), FaseTransformacion::Segunda, EstatusInspeccion::Rechazado, ['subetapa' => Subetapa::ArmadoVestido]);
    $armado->puntos()->create(['punto_id' => $faltaVestido->id, 'resultado' => 'no_ok', 'valor_numerico' => 3]);
    $armado->puntos()->create(['punto_id' => $bisel->id, 'resultado' => 'no_ok', 'valor_texto' => 'Con defecto']);

    $pareto = propsDelTablero($this)['tablero']['operacion']['pareto'];

    expect($pareto['armado'])->toEqual([
        ['causa' => 'Falta de vestido', 'n' => 3],
        ['causa' => 'Ángulo de bisel', 'n' => 1],
    ])->and($pareto['p2_deftypes'])->toBe([]);
});

test('las juntas se siguen entre reinspecciones de la misma pieza', function () {
    $pieza = piezaDelTablero(ObraDelPortal::factory()->create());

    $primera = inspeccionDelTablero($pieza, FaseTransformacion::Segunda, EstatusInspeccion::Rechazado, ['fecha' => now()->subDay()]);
    Junta::factory()->create(['inspeccion_id' => $primera->id, 'identificador' => 'S1', 'resultado' => ResultadoJunta::ConDefecto]);
    Junta::factory()->create(['inspeccion_id' => $primera->id, 'identificador' => 'S2']);

    $segunda = inspeccionDelTablero($pieza, FaseTransformacion::Segunda, EstatusInspeccion::Liberado, ['numero_inspeccion' => 2]);
    Junta::factory()->create(['inspeccion_id' => $segunda->id, 'identificador' => 'S1', 'intento' => 2]);

    expect(propsDelTablero($this)['tablero']['resumen']['juntas'])
        ->toEqual(['n' => 2, 'fpy' => 50, 'final' => 100, 'reproceso' => 50]);
});

test('un filtro mal escrito no llega al calculo', function () {
    $this->actingAs(usuarioDelTablero())
        ->get(route('admin.qal.dashboard', ['semana' => 'la 34']))
        ->assertSessionHasErrors('semana');
});

test('sin inspecciones el tablero abre en cero, sin inventar porcentajes', function () {
    $tablero = propsDelTablero($this)['tablero'];

    expect($tablero['resumen']['inspecciones']['total'])->toBe(0)
        ->and($tablero['resumen']['rechazo'])->toEqual(['2ª' => null, '3ª' => null])
        ->and($tablero['resumen']['juntas'])->toBeNull()
        ->and($tablero['tasas']['elem']['cobertura']['pct'])->toBeNull()
        ->and($tablero['operacion']['resultadoPorObra'])->toBe([])
        ->and($tablero['estadistica']['cartaP'])->toBeNull()
        ->and($tablero['estadistica']['capacidad'])->toBe([]);
});

test('defectos de pintura van al pareto de 3a y no al de soldadura', function () {
    $pieza = piezaDelTablero(ObraDelPortal::factory()->create());
    $escurrimiento = Defecto::factory()->deAmbito(AmbitoDefecto::Pintura)->create(['nombre' => 'Escurrimiento']);

    inspeccionDelTablero($pieza, FaseTransformacion::Tercera, EstatusInspeccion::Rechazado)
        ->defectos()->create(['defecto_id' => $escurrimiento->id, 'cantidad' => 2]);

    $pareto = propsDelTablero($this)['tablero']['operacion']['pareto'];

    expect($pareto['p3_deftypes'])->toEqual([['causa' => 'Escurrimiento', 'n' => 2]])
        ->and($pareto['p2_deftypes'])->toBe([]);
});

/** Fecha con su año y semana ISO, que es como la guarda la captura. */
function fechaDelTablero(string $fecha): array
{
    $dia = Carbon::parse($fecha);

    return ['fecha' => $dia, 'anio' => $dia->isoWeekYear(), 'semana' => $dia->isoWeek()];
}

test('la carta-p cuenta inspecciones por semana', function () {
    $obra = ObraDelPortal::factory()->create();

    // Semana 33: una de dos rechazada. Semana 34: las dos liberadas.
    inspeccionDelTablero(piezaDelTablero($obra), FaseTransformacion::Segunda, EstatusInspeccion::Rechazado, fechaDelTablero('2026-08-12'));
    inspeccionDelTablero(piezaDelTablero($obra), FaseTransformacion::Segunda, EstatusInspeccion::Liberado, fechaDelTablero('2026-08-12'));
    inspeccionDelTablero(piezaDelTablero($obra), FaseTransformacion::Segunda, EstatusInspeccion::Liberado, fechaDelTablero('2026-08-19'));
    inspeccionDelTablero(piezaDelTablero($obra), FaseTransformacion::Segunda, EstatusInspeccion::Liberado, fechaDelTablero('2026-08-19'));

    $carta = propsDelTablero($this)['tablero']['estadistica']['cartaP'];

    expect($carta)->toMatchArray(['preliminar' => true, 'subgrupos' => 2, 'pbar' => 25])
        ->and(collect($carta['puntos'])->map(fn (array $punto): array => [$punto['semana'], $punto['p'], $punto['n'], $punto['lcl']])->all())
        ->toEqual([['2026-S33', 50, 2, 0], ['2026-S34', 0, 2, 0]]);
});

test('el espesor se lee en margen y el cpk va por espesor requerido', function () {
    $obra = ObraDelPortal::factory()->create(['no' => 'T4']);

    foreach ([12, 11, 9] as $mils) {
        inspeccionDelTablero(piezaDelTablero($obra), FaseTransformacion::Tercera, EstatusInspeccion::Liberado)
            ->pintura()->create(['espesor_requerido_mils' => 10, 'promedio_mils' => $mils]);
    }
    // Pintada sin registrar el espesor: cuenta en la cobertura, no en la media.
    inspeccionDelTablero(piezaDelTablero($obra), FaseTransformacion::Tercera, EstatusInspeccion::Liberado);

    $estadistica = propsDelTablero($this)['tablero']['estadistica'];

    expect($estadistica['margen'])->toMatchArray(['piezas' => 3, 'piezasPintura' => 4])
        ->and($estadistica['margen']['resumen'])->toMatchArray([
            'medianaPct' => 10,
            'medianaMils' => 1,
            'minPct' => -10,
            'maxPct' => 20,
            'bajoMinimo' => 1,
        ])
        ->and($estadistica['capacidad'])->toEqual([[
            'requerido' => 10,
            'obras' => ['T4'],
            'n' => 3,
            'media' => 10.67,
            'sigma' => 1.53,
            'cpk' => 0.15,
            'fuera' => 1,
        ]])
        ->and($estadistica['coberturaEspesor'])->toEqual(['con' => 3, 'total' => 4])
        ->and(collect($estadistica['descriptiva'])->firstWhere('clave', 'espesor'))
        ->toMatchArray(['conDato' => 3, 'universo' => 4]);
});

test('el chi-cuadrado compara el veredicto final dentro de una etapa', function () {
    $obra = ObraDelPortal::factory()->create();

    // Diez de armado, cinco rechazadas al cierre; diez de soldado, todas liberadas.
    foreach (range(1, 10) as $n) {
        inspeccionDelTablero(piezaDelTablero($obra), FaseTransformacion::Segunda, $n <= 5 ? EstatusInspeccion::Rechazado : EstatusInspeccion::Pendiente, [
            'subetapa' => Subetapa::ArmadoVestido,
        ]);
        inspeccionDelTablero(piezaDelTablero($obra), FaseTransformacion::Segunda, EstatusInspeccion::Liberado);
    }

    $estadistica = propsDelTablero($this)['tablero']['estadistica'];
    $prueba = $estadistica['pruebas']['2ª']['p2_subetapa'];

    expect($prueba)->toMatchArray([
        'estado' => 'ok',
        'chi2' => 6.67,
        'gl' => 1,
        'piezas' => 20,
        'base' => 25,
        'v' => 0.58,
        'celdasBajas' => 2,
    ])
        ->and($prueba['p'])->toBeLessThan(0.05)
        ->and($prueba['detalle'][0])->toMatchArray(['categoria' => 'Armado / Vestido', 'n' => 10, 'rechazadas' => 5, 'pct' => 50])
        // Sin piezas de pintura no hay nada que probar en 3ª.
        ->and($estadistica['pruebas']['3ª']['obra']['estado'])->toBe('sin_muestra')
        // Todo cayó en una semana: no hay tendencia que medir todavía.
        ->and($estadistica['tendencias']['2ª'])->toMatchArray(['estado' => 'pocas_semanas', 'semanas' => 1]);
});

test('el uso de campos cuenta los blancos aparte y no los mete en el defecto', function () {
    $obra = ObraDelPortal::factory()->create();
    $bisel = PuntoInspeccion::factory()->create([
        'etiqueta' => 'Ángulo de bisel',
        'seccion' => 'Preparación de juntas',
        'fase' => FaseTransformacion::Segunda,
    ]);
    // Un punto que sólo describe no dice si hubo defecto: no se le pregunta.
    PuntoInspeccion::factory()->create([
        'etiqueta' => 'Tipo de desviación',
        'fase' => FaseTransformacion::Segunda,
        'opciones' => [['valor' => 'Flecha', 'resultado' => null]],
    ]);

    // Diez inspecciones: seis contestadas, cuatro en blanco.
    $respuestas = ['ok', 'ok', 'ok', 'ok', 'no_ok', 'no_aplica'];
    foreach (range(0, 9) as $n) {
        $inspeccion = inspeccionDelTablero(piezaDelTablero($obra), FaseTransformacion::Segunda, EstatusInspeccion::Liberado);

        if (isset($respuestas[$n])) {
            $inspeccion->puntos()->create(['punto_id' => $bisel->id, 'resultado' => $respuestas[$n], 'valor_texto' => 'x']);
        }
    }

    $campos = collect(propsDelTablero($this)['tablero']['diagnostico']['usoCampos']);

    expect($campos->firstWhere('campo', 'Ángulo de bisel'))->toEqual([
        'fase' => '2ª',
        'bloque' => 'Preparación de juntas',
        'campo' => 'Ángulo de bisel',
        'n' => 10,
        'ok' => 4,
        'defecto' => 1,
        'noAplica' => 1,
        'vacio' => 4,
    ])->and($campos->firstWhere('campo', 'Tipo de desviación'))->toBeNull();
});

test('los factores de riesgo comparan cada categoria con su propia etapa', function () {
    $naveA = ObraDelPortal::factory()->create(['no' => 'NAVE A']);
    $naveB = ObraDelPortal::factory()->create(['no' => 'NAVE B']);

    foreach (range(1, 5) as $n) {
        inspeccionDelTablero(piezaDelTablero($naveA), FaseTransformacion::Segunda, $n <= 4 ? EstatusInspeccion::Rechazado : EstatusInspeccion::Liberado);
        inspeccionDelTablero(piezaDelTablero($naveB), FaseTransformacion::Segunda, EstatusInspeccion::Liberado);
    }

    $factores = collect(propsDelTablero($this)['tablero']['diagnostico']['factores']);

    // La sub-etapa rechaza igual que su etapa (1×): no es señal y no sale.
    expect($factores->pluck('valor')->all())->toBe(['NAVE A', 'NAVE B'])
        ->and($factores->first())->toMatchArray([
            'fase' => '2ª',
            'factor' => 'Obra',
            'piezas' => 5,
            'rechazadas' => 4,
            'tasa' => 80,
            'base' => 40,
            'rr' => 2,
            // Cuatro de cinco puede ser azar: con tan pocas piezas no se afirma nada.
            'evidencia' => 'nada',
        ])
        ->and($factores->first()['p'])->toEqualWithDelta(0.174, 0.001);
});

test('el perfil y el mapa de riesgo miden cada defecto contra la mezcla de su etapa', function () {
    $t4 = ObraDelPortal::factory()->create(['no' => 'T4']);
    $parks = ObraDelPortal::factory()->create(['no' => 'PARKS']);
    $socavado = Defecto::factory()->create(['nombre' => 'Socavado']);
    $poro = Defecto::factory()->create(['nombre' => 'Porosidad']);

    $conDefectos = function (ObraDelPortal $obra, array $defectos): void {
        $inspeccion = inspeccionDelTablero(piezaDelTablero($obra), FaseTransformacion::Segunda, EstatusInspeccion::Liberado);

        foreach ($defectos as $defecto => $cantidad) {
            $inspeccion->defectos()->create(['defecto_id' => $defecto, 'cantidad' => $cantidad]);
        }
    };
    $conDefectos($t4, [$socavado->id => 3]);
    $conDefectos($t4, [$socavado->id => 1, $poro->id => 1]);
    $conDefectos($t4, [$poro->id => 1]);
    $conDefectos($t4, []);
    foreach (range(1, 4) as $n) {
        $conDefectos($parks, [$poro->id => 3]);
    }

    $diagnostico = propsDelTablero($this)['tablero']['diagnostico'];
    $t4Perfil = collect($diagnostico['perfil']['obra'])->firstWhere('nombre', 'T4');

    // En la etapa el socavado es 4 de 18 defectos; en T4, 4 de 6: tres veces más.
    expect($t4Perfil)->toMatchArray([
        'fase' => '2ª',
        'piezas' => 4,
        'defectos' => 6,
        'defPorPieza' => 1.5,
        'rechazo' => 0,
        'caracteristico' => ['defecto' => 'Socavado', 'indice' => 3],
    ])
        ->and($t4Perfil['top'][0])->toEqual(['defecto' => 'Socavado', 'n' => 4, 'share' => 66.7])
        ->and($diagnostico['mapas'][0])->toMatchArray([
            'fase' => '2ª',
            'totalDefectos' => 18,
            'tiposDefecto' => 2,
            'piezasEtapa' => 8,
            'usaMagnitud' => true,
        ])
        ->and(collect($diagnostico['mapas'][0]['items'])->map(fn (array $item): array => [$item['defecto'], $item['defectos'], $item['frecuencia']])->all())
        ->toEqual([['Porosidad', 14, 77.8], ['Socavado', 4, 22.2]])
        ->and($diagnostico['mapas'][1]['items'])->toBe([]);
});
