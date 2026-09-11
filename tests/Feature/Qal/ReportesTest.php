<?php

use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use App\Models\Concepto;
use App\Models\Prod\Pieza;
use App\Models\Qal\Defecto;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Junta;
use App\Models\Qal\PuntoInspeccion;
use App\Models\User;
use App\Services\Qal\Formatos\ArmadoVestido;
use App\Services\Qal\Formatos\FiltrosDeReporte;
use App\Services\Qal\Formatos\MapeoDeJuntas;
use App\Services\Qal\Formatos\Soldadura;
use App\Services\Qal\Formatos\VisualSoldadura;
use Database\Seeders\QalPuntosInspeccionSeeder;
use Spatie\Permission\Models\Permission;

/**
 * Los formatos PDF de 2ª: la pantalla, el PDF y, sobre todo, las reglas con
 * que se arma cada hoja. Se comprueba `datos()` y no el dibujo del PDF.
 */
beforeEach(fn () => $this->seed(QalPuntosInspeccionSeeder::class));

function usuarioDeReportes(): User
{
    Permission::firstOrCreate(['name' => 'qal.reportes.ver', 'guard_name' => 'web']);

    $usuario = User::factory()->create();
    $usuario->givePermissionTo('qal.reportes.ver');

    return $usuario;
}

function piezaDeMarca(Concepto $marca, string $qr): Pieza
{
    return Pieza::factory()->create(['concepto_id' => $marca->id, 'qr' => $qr]);
}

function inspeccionDe(Pieza $pieza, Subetapa $subetapa, array $datos = []): Inspeccion
{
    return Inspeccion::factory()->dePieza($pieza, FaseTransformacion::Segunda, $subetapa)->create($datos);
}

function responder(Inspeccion $inspeccion, string $clave, string $resultado, ?float $numero = null): void
{
    $inspeccion->puntos()->create([
        'punto_id' => PuntoInspeccion::query()->where('clave', $clave)->value('id'),
        'resultado' => $resultado,
        'valor_texto' => $numero === null ? ($resultado === 'ok' ? 'OK' : 'Con defecto') : null,
        'valor_numerico' => $numero,
    ]);
}

function defecto(Inspeccion $inspeccion, string $nombre, int $cantidad = 1): void
{
    $defecto = Defecto::query()->firstOrCreate(['ambito' => 'soldadura', 'nombre' => $nombre], ['activo' => true]);
    $inspeccion->defectos()->create(['defecto_id' => $defecto->id, 'cantidad' => $cantidad]);
}

test('la pantalla de reportes pide su permiso y ofrece primero los formatos del dosier', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.qal.reportes.index'))->assertForbidden();

    $this->actingAs(usuarioDeReportes())
        ->get(route('admin.qal.reportes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/calidad/reportes/index')
            ->has('formatos', 4)
            ->where('formatos.0.destino', 'dosier')
            ->where('formatos.3.destino', 'interno')
            ->where('filtros.formato', 'visual-soldadura')
            ->where('filtros.estatus', 'liberadas')
            ->where('opciones', null));
});

test('con la obra elegida ofrece solo dias, semanas e inspectores de la etapa del formato', function () {
    $marca = Concepto::factory()->create();
    $pieza = piezaDeMarca($marca, '155001');
    $soldado = inspeccionDe($pieza, Subetapa::Soldado, ['fecha' => '2026-09-08', 'anio' => 2026, 'semana' => 37]);
    inspeccionDe($pieza, Subetapa::ArmadoVestido, ['fecha' => '2026-08-03', 'anio' => 2026, 'semana' => 32]);

    $this->actingAs(usuarioDeReportes())
        ->get(route('admin.qal.reportes.index', ['formato' => 'soldadura', 'obra' => $marca->obra_id]))
        ->assertInertia(fn ($page) => $page
            ->where('filtros.estatus', 'todas')
            ->where('filtros.vista', 'historico')
            ->where('opciones.total', 1)
            ->where('opciones.fechas', ['2026-09-08'])
            ->where('opciones.semanas', ['2026-S37'])
            ->where('opciones.inspectores.0.valor', (string) $soldado->inspector_id));
});

test('el pdf se previsualiza en linea y se descarga como adjunto', function () {
    $marca = Concepto::factory()->create();
    inspeccionDe(piezaDeMarca($marca, '155001'), Subetapa::Soldado);
    $usuario = usuarioDeReportes();

    $respuesta = $this->actingAs($usuario)->get(route('admin.qal.reportes.pdf', ['formato' => 'soldadura', 'obra' => $marca->obra_id]));

    $respuesta->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($respuesta->getContent())->toStartWith('%PDF')
        ->and($respuesta->headers->get('content-disposition'))->toContain('inline');

    $descarga = $this->get(route('admin.qal.reportes.pdf', ['formato' => 'visual-soldadura', 'obra' => $marca->obra_id, 'descargar' => 1]));

    expect($descarga->headers->get('content-disposition'))->toContain('attachment');
});

test('cada formato de 2a genera su pdf aunque este vacio', function (string $formato) {
    $marca = Concepto::factory()->create();

    $this->actingAs(usuarioDeReportes())
        ->get(route('admin.qal.reportes.pdf', ['formato' => $formato, 'obra' => $marca->obra_id, 'pieza' => 1]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
})->with(['visual-soldadura', 'mapeo', 'soldadura', 'armado']);

test('un formato que no existe es 404 y los filtros mal puestos se rechazan', function () {
    $marca = Concepto::factory()->create();
    $this->actingAs(usuarioDeReportes());

    $this->get('/admin/calidad/reportes/inexistente?obra='.$marca->obra_id)->assertNotFound();
    $this->get(route('admin.qal.reportes.pdf', ['formato' => 'soldadura']))->assertSessionHasErrors('obra');
    $this->get(route('admin.qal.reportes.pdf', ['formato' => 'soldadura', 'obra' => $marca->obra_id, 'periodo' => 'semana', 'semana' => '37']))
        ->assertSessionHasErrors('semana');
    $this->get(route('admin.qal.reportes.pdf', ['formato' => 'mapeo', 'obra' => $marca->obra_id]))->assertSessionHasErrors('pieza');
});

test('armado enseña cada intento en el historico y el ultimo en la hoja final', function () {
    $marca = Concepto::factory()->create(['marca' => 'SX-CM1-1']);
    $pieza = piezaDeMarca($marca, '155001');
    $rechazo = inspeccionDe($pieza, Subetapa::ArmadoVestido, ['estatus' => EstatusInspeccion::Rechazado, 'fecha' => now()->subDay()]);
    responder($rechazo, 'p2_bisel', 'no_ok');
    $segunda = inspeccionDe($pieza, Subetapa::ArmadoVestido, ['estatus' => EstatusInspeccion::Pendiente, 'numero_inspeccion' => 2]);
    responder($segunda, 'p2_bisel', 'ok');
    responder($segunda, 'p2_faltavest', 'ok', 0);

    $formato = app(ArmadoVestido::class);
    $historico = $formato->datos(new FiltrosDeReporte(obraId: $marca->obra_id, vista: 'historico'));
    $final = $formato->datos(new FiltrosDeReporte(obraId: $marca->obra_id, estatus: 'liberadas', vista: 'final'));

    expect($historico['renglones'])->toHaveCount(2)
        ->and($historico['renglones'][0]['preparacion'][0]['texto'])->toBe('D')
        ->and($historico['nota'])->toBeNull()
        // En armado «liberadas» es salir bien: pendiente, que pasa a soldado.
        ->and($final['renglones'])->toHaveCount(1)
        ->and($final['renglones'][0]['inspeccion'])->toBe(2)
        ->and($final['renglones'][0]['resultado']['texto'])->toBe('Aceptado')
        ->and($final['renglones'][0]['preparacion'][0]['texto'])->toBe('A')
        ->and($final['nota'])->toContain('1 pieza(s) necesitaron re-inspección')
        ->and($final['totales'])->toMatchArray(['piezas' => 1, 'aceptadas' => 1, 'rechazadas' => 0]);
});

test('soldadura suma defectos por elemento y arrastra los elementos medidos antes', function () {
    $marca = Concepto::factory()->create();
    $pieza = piezaDeMarca($marca, '155001');
    $primera = inspeccionDe($pieza, Subetapa::Soldado, ['estatus' => EstatusInspeccion::Rechazado, 'fecha' => now()->subDay()]);
    responder($primera, 'p2_elem', 'ok', 10);
    defecto($primera, 'Socavación', 2);
    defecto($primera, 'Porosidad / poros', 1);
    // La reinspección sólo cambió el estatus: no volvió a contar elementos.
    inspeccionDe($pieza, Subetapa::Soldado, ['numero_inspeccion' => 2]);

    $formato = app(Soldadura::class);
    $historico = $formato->datos(new FiltrosDeReporte(obraId: $marca->obra_id));
    $final = $formato->datos(new FiltrosDeReporte(obraId: $marca->obra_id, vista: 'final'));

    expect($historico['renglones'][0]['defectos'])->toBe(3)
        ->and($historico['renglones'][0]['tipos'])->toContain('Socavación: 2')
        ->and($historico['totales'])->toMatchArray(['elementos' => 10.0, 'defectos' => 3, 'por_elemento' => '0.300', 'rechazadas' => 1, 'liberadas' => 1])
        ->and($final['renglones'])->toHaveCount(1)
        ->and($final['renglones'][0]['elementos'])->toBe(10.0)
        ->and($final['renglones'][0]['defectos'])->toBe(0);
});

test('en la hoja del dosier una marca liberada sale sin defectos ni observaciones', function () {
    $marca = Concepto::factory()->create(['marca' => 'SX-TP1-1']);
    $pieza = piezaDeMarca($marca, '155001');
    $armado = inspeccionDe($pieza, Subetapa::ArmadoVestido, ['estatus' => EstatusInspeccion::Pendiente]);
    responder($armado, 'p2_bisel', 'ok');
    responder($armado, 'p2_respaldo', 'no_aplica');
    // Liberada, pero con el precalentamiento que nadie desmarcó y un comentario viejo.
    $soldado = inspeccionDe($pieza, Subetapa::Soldado, ['estatus' => EstatusInspeccion::Liberado, 'observaciones' => 'retocar cordón']);
    responder($soldado, 'p2_precal', 'no_ok');
    defecto($soldado, 'Socavación');

    $datos = app(VisualSoldadura::class)->datos(new FiltrosDeReporte(obraId: $marca->obra_id, estatus: 'liberadas', vista: 'final'));
    $fila = $datos['renglones'][0];

    expect($datos['renglones'])->toHaveCount(1)
        ->and($fila['marca'])->toBe('SX-TP1-1')
        ->and(array_column($fila['antes'], 'texto'))->toBe(['A', 'A', '—', 'N/A', '—', '—'])
        ->and($fila['durante'][0]['texto'])->toBe('A')
        ->and(array_unique(array_column($fila['despues'], 'texto')))->toBe(['DN'])
        ->and($fila['observaciones'])->toBe('N/A');
});

test('una marca rechazada lleva sus defectos a su columna y los que no tienen columna a observaciones', function () {
    $marca = Concepto::factory()->create();
    $unaPieza = inspeccionDe(piezaDeMarca($marca, '155001'), Subetapa::Soldado, ['estatus' => EstatusInspeccion::Rechazado]);
    defecto($unaPieza, 'Socavación', 2);
    defecto($unaPieza, 'Falta de penetración');
    inspeccionDe(piezaDeMarca($marca, '155002'), Subetapa::Soldado, ['estatus' => EstatusInspeccion::Liberado]);

    $datos = app(VisualSoldadura::class)->datos(new FiltrosDeReporte(obraId: $marca->obra_id, estatus: 'todas', vista: 'final'));
    $fila = $datos['renglones'][0];
    $columna = fn (string $nombre): string => $fila['despues'][array_search($nombre, $datos['despues'], true)]['texto'];

    expect($fila['piezas'])->toBe(2)
        ->and($columna('Socavado'))->toBe('D')
        ->and($columna('Grieta'))->toBe('DN')
        // Sin inspección de armado no se inventa un aceptado.
        ->and($fila['antes'][1]['texto'])->toBe('—')
        ->and($fila['observaciones'])->toContain('Otros defectos: Falta de penetración: 1');
});

test('la hoja de revision recupera los defectos de un intento anterior', function () {
    $marca = Concepto::factory()->create();
    $pieza = piezaDeMarca($marca, '155001');
    $primera = inspeccionDe($pieza, Subetapa::Soldado, ['estatus' => EstatusInspeccion::Rechazado, 'fecha' => now()->subWeek(), 'observaciones' => 'socavado en patín']);
    responder($primera, 'p2_precal', 'no_ok');
    defecto($primera, 'Socavación');
    $segunda = inspeccionDe($pieza, Subetapa::Soldado, ['estatus' => EstatusInspeccion::Liberado, 'numero_inspeccion' => 2]);
    responder($segunda, 'p2_precal', 'ok');

    $formato = app(VisualSoldadura::class);
    // Sólo la semana de la liberación: el rechazo quedó fuera del periodo.
    $semana = sprintf('%d-S%02d', $segunda->anio, $segunda->semana);
    $revision = $formato->datos(new FiltrosDeReporte(obraId: $marca->obra_id, periodo: 'semana', semana: $semana, estatus: 'liberadas', vista: 'revision'));
    $final = $formato->datos(new FiltrosDeReporte(obraId: $marca->obra_id, periodo: 'semana', semana: $semana, estatus: 'liberadas', vista: 'final'));
    $socavado = array_search('Socavado', $revision['despues'], true);

    expect($revision['renglones'][0]['durante'][0]['texto'])->toBe('D')
        ->and($revision['renglones'][0]['despues'][$socavado]['texto'])->toBe('D')
        ->and($revision['renglones'][0]['observaciones'])->toContain('socavado en patín')
        ->and($final['renglones'][0]['durante'][0]['texto'])->toBe('A')
        ->and($final['renglones'][0]['despues'][$socavado]['texto'])->toBe('DN');
});

test('el mapeo ordena las juntas por su numero y de cada una cuenta el ultimo intento', function () {
    $marca = Concepto::factory()->create();
    $pieza = piezaDeMarca($marca, '155001');
    $inspeccion = inspeccionDe($pieza, Subetapa::Soldado);
    $grieta = PuntoInspeccion::query()->where('clave', 'm_grieta')->value('id');

    $diez = Junta::factory()->create(['inspeccion_id' => $inspeccion->id, 'identificador' => 'J10']);
    $diez->puntos()->create(['punto_id' => $grieta, 'resultado' => 'ok']);
    $dos = Junta::factory()->create(['inspeccion_id' => $inspeccion->id, 'identificador' => 'J2']);
    $dos->puntos()->create(['punto_id' => $grieta, 'resultado' => 'ok']);
    // La J2 se reparó y se volvió a mirar en la segunda inspección de la pieza, con grieta.
    $reinspeccion = inspeccionDe($pieza, Subetapa::Soldado, ['numero_inspeccion' => 2]);
    $reparada = Junta::factory()->create(['inspeccion_id' => $reinspeccion->id, 'identificador' => 'J2', 'intento' => 2]);
    $reparada->puntos()->create(['punto_id' => $grieta, 'resultado' => 'no_ok']);

    $datos = app(MapeoDeJuntas::class)->datos(new FiltrosDeReporte(obraId: $marca->obra_id, piezaId: $pieza->id));
    $columna = array_search('Grieta', $datos['puntos'], true);

    expect(array_column($datos['renglones'], 'junta'))->toBe(['J2', 'J10'])
        ->and($datos['renglones'][0]['celdas'][$columna]['texto'])->toBe('D')
        ->and($datos['renglones'][1]['celdas'][$columna]['texto'])->toBe('A')
        ->and($datos['renglones'][1]['celdas'][0]['texto'])->toBe('—')
        ->and($datos['creador_id'])->toBe($reinspeccion->inspector->usuario_id);
});
