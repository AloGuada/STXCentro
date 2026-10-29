<?php

use App\Enums\Qal\FaseTransformacion;
use App\Exports\Qal\Registro2InspeccionesExport;
use App\Exports\Qal\Registro2SublotesExport;
use App\Models\Concepto;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Inspector;
use App\Models\Qal\Sublote;
use App\Models\User;
use App\Models\Usuario;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;

/**
 * Registro 2: la misma base que Registros, con los filtros del backlog.
 *
 * Se comprueba lo que cambia —periodo, obra validada, inspectores con
 * historial, estatus sin disfrazar— y que lo que se hereda de Registros siga
 * igual: registro ≠ pieza, la última inspección del sublote manda y exportar
 * es permiso aparte.
 */
function auditorDeRegistro2(array $permisos = ['qal.registros.ver']): User
{
    foreach ($permisos as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo($permisos);

    return $usuario;
}

test('registro 2 pide el mismo permiso que registros', function () {
    $this->actingAs(auditorDeRegistro2(['qal.inspecciones.crear']))
        ->get(route('admin.qal.registros2.index'))
        ->assertForbidden();

    $this->actingAs(auditorDeRegistro2())
        ->get(route('admin.qal.registros2.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/calidad/registros2/index')
            ->has('registros.data', 0)
            ->where('conteo.registros', 0));
});

test('los indicadores separan registros de piezas únicas', function () {
    $concepto = Concepto::factory()->create();
    Inspeccion::factory()->deMarca($concepto, 1)->rechazada()->create();
    Inspeccion::factory()->deMarca($concepto, 1)->create(['numero_inspeccion' => 2]);
    Inspeccion::factory()->deMarca($concepto, 2)->create();

    $this->actingAs(auditorDeRegistro2())
        ->get(route('admin.qal.registros2.index', ['estatus' => 'liberado']))
        ->assertInertia(fn ($page) => $page
            ->where('conteo.registros', 2)
            ->where('conteo.piezas', 2)
            ->where('conteo.total', 3)
            ->has('registros.data', 2));
});

test('cada filtro funciona solo y combinado', function () {
    $concepto = Concepto::factory()->create(['marca' => 'PJ-CM1-10']);
    $inspector = Inspector::factory()->create();
    Inspeccion::factory()->deMarca($concepto)->rechazada()->create(['inspector_id' => $inspector->id]);
    Inspeccion::factory()->deMarca($concepto, 2)->create(['fase' => FaseTransformacion::Tercera]);
    Inspeccion::factory()->deMarca(Concepto::factory()->create(['marca' => 'TV-TP3-2']))->rechazada()->create();
    $usuario = auditorDeRegistro2();

    $cuantos = fn (array $filtros) => $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', $filtros))
        ->viewData('page')['props']['registros']['data'];

    expect($cuantos(['obra' => $concepto->obra_id]))->toHaveCount(2)
        ->and($cuantos(['fase' => '3ª']))->toHaveCount(1)
        ->and($cuantos(['inspector' => $inspector->id]))->toHaveCount(1)
        ->and($cuantos(['estatus' => 'rechazado']))->toHaveCount(2)
        ->and($cuantos(['obra' => $concepto->obra_id, 'estatus' => 'rechazado']))->toHaveCount(1)
        ->and($cuantos(['obra' => $concepto->obra_id, 'fase' => '3ª', 'estatus' => 'rechazado']))->toHaveCount(0);
});

test('el periodo filtra por todo, últimos 7 y 30 días, una semana o un día', function () {
    $this->travelTo('2026-10-29 10:00');
    $concepto = Concepto::factory()->create();
    // Hoy, el primer día de los últimos 7, un día de la semana 43, uno de los
    // últimos 30 y uno que queda fuera de todos.
    foreach (['2026-10-29', '2026-10-23', '2026-10-20', '2026-10-01', '2026-09-01'] as $consecutivo => $fecha) {
        Inspeccion::factory()->deMarca($concepto, $consecutivo + 1)->create(['fecha' => $fecha]);
    }
    $usuario = auditorDeRegistro2();

    $periodo = fn (array $filtros) => $this->actingAs($usuario)->get(route('admin.qal.registros2.index', $filtros));

    $periodo([])->assertInertia(fn ($page) => $page
        ->where('filtros.periodo', 'todo')
        ->where('filtros.desde', null)
        ->has('registros.data', 5));

    $periodo(['periodo' => '7d'])->assertInertia(fn ($page) => $page
        ->where('filtros.desde', '2026-10-23')
        ->where('filtros.hasta', '2026-10-29')
        ->has('registros.data', 2)
        ->where('conteo.registros', 2));

    $periodo(['periodo' => '30d'])->assertInertia(fn ($page) => $page
        ->where('filtros.desde', '2026-09-30')
        ->has('registros.data', 4));

    // La semana 43 de 2026 va del lunes 19 al domingo 25 de octubre.
    $periodo(['periodo' => 'semana', 'semana' => '2026-W43'])->assertInertia(fn ($page) => $page
        ->where('filtros.semana', '2026-W43')
        ->where('filtros.desde', '2026-10-19')
        ->where('filtros.hasta', '2026-10-25')
        ->has('registros.data', 2));

    $periodo(['periodo' => 'fecha', 'dia' => '2026-10-01'])->assertInertia(fn ($page) => $page
        ->where('filtros.dia', '2026-10-01')
        ->has('registros.data', 1));
});

test('una semana o una fecha sin valor válido es la semana en curso o hoy', function () {
    $this->travelTo('2026-10-29 10:00');
    $concepto = Concepto::factory()->create();
    Inspeccion::factory()->deMarca($concepto)->create(['fecha' => '2026-10-27']);
    Inspeccion::factory()->deMarca($concepto, 2)->create(['fecha' => '2026-10-29']);
    $usuario = auditorDeRegistro2();

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', ['periodo' => 'semana', 'semana' => '2026-W60']))
        ->assertInertia(fn ($page) => $page
            ->where('filtros.semana', '2026-W44')
            ->where('filtros.desde', '2026-10-26')
            ->has('registros.data', 2));

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', ['periodo' => 'fecha', 'dia' => '2026-02-30']))
        ->assertInertia(fn ($page) => $page
            ->where('filtros.dia', '2026-10-29')
            ->has('registros.data', 1));
});

test('el selector de semanas trae las que tienen capturas, la más reciente primero', function () {
    $concepto = Concepto::factory()->create();
    Inspeccion::factory()->deMarca($concepto)->create(['fecha' => '2026-10-20', 'anio' => 2026, 'semana' => 43]);
    Inspeccion::factory()->deMarca($concepto, 2)->create(['fecha' => '2026-10-21', 'anio' => 2026, 'semana' => 43]);
    Sublote::factory()->create(['fecha' => '2025-12-29', 'anio' => 2026, 'semana' => 1]);

    $this->actingAs(auditorDeRegistro2())
        ->get(route('admin.qal.registros2.index'))
        ->assertInertia(fn ($page) => $page
            ->has('semanas', 2)
            ->where('semanas.0', ['valor' => '2026-W43', 'desde' => '2026-10-19', 'hasta' => '2026-10-25'])
            ->where('semanas.1', ['valor' => '2026-W01', 'desde' => '2025-12-29', 'hasta' => '2026-01-04']));
});

test('los valores inválidos se descartan en vez de dejar la tabla vacía', function () {
    $concepto = Concepto::factory()->create();
    Inspeccion::factory()->deMarca($concepto)->create();

    $this->actingAs(auditorDeRegistro2())
        ->get(route('admin.qal.registros2.index', [
            'obra' => 999999,
            'fase' => '9ª',
            'estatus' => 'inventado',
            'periodo' => 'siempre',
            'dia' => '2026-10-01',
            'sort_by' => 'password',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filtros.obra', null)
            ->where('filtros.fase', null)
            ->where('filtros.estatus', null)
            ->where('filtros.periodo', 'todo')
            ->where('filtros.dia', null)
            ->where('filtros.desde', null)
            ->where('filtros.sort_by', 'fecha')
            ->has('registros.data', 1));
});

test('la búsqueda encuentra por marca o folio, respeta los filtros y no se rompe con comodines', function () {
    $concepto = Concepto::factory()->create(['marca' => 'PJ-CM1-10']);
    $rechazada = Inspeccion::factory()->deMarca($concepto)->rechazada()->create();
    Inspeccion::factory()->deMarca($concepto, 2)->create();
    Inspeccion::factory()->deMarca(Concepto::factory()->create(['marca' => 'TV-TP3-2']))->create();
    $usuario = auditorDeRegistro2();

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', ['buscar' => 'cm1', 'estatus' => 'rechazado']))
        ->assertInertia(fn ($page) => $page
            ->has('registros.data', 1)
            ->where('registros.data.0.marca', 'PJ-CM1-10'));

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', ['buscar' => $rechazada->folio]))
        ->assertInertia(fn ($page) => $page->has('registros.data', 1)->where('registros.data.0.id', $rechazada->id));

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', ['buscar' => "x' OR '1'='1"]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('registros.data', 0));

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', ['buscar' => '   ']))
        ->assertInertia(fn ($page) => $page->where('filtros.buscar', null)->has('registros.data', 3));
});

test('el estatus se entrega tal como está guardado', function () {
    $concepto = Concepto::factory()->create();
    Inspeccion::factory()->deMarca($concepto)->rechazada()->create();

    $this->actingAs(auditorDeRegistro2())
        ->get(route('admin.qal.registros2.index'))
        ->assertInertia(fn ($page) => $page->where('registros.data.0.estatus', 'rechazado'));
});

test('los inspectores del filtro son los que tienen registros, activos o dados de baja', function () {
    $activo = Inspector::factory()->create(['usuario_id' => Usuario::factory()->create(['name' => 'Ana Activa'])->id]);
    $deBaja = Inspector::factory()->create(['usuario_id' => Usuario::factory()->dadoDeBaja()->create(['name' => 'Beto Baja'])->id]);
    $conSublote = Inspector::factory()->create(['usuario_id' => Usuario::factory()->create(['name' => 'Ceci Lotes'])->id]);
    Inspector::factory()->create(['usuario_id' => Usuario::factory()->create(['name' => 'Dani Nada'])->id]);
    $concepto = Concepto::factory()->create();
    Inspeccion::factory()->deMarca($concepto)->create(['inspector_id' => $activo->id]);
    Inspeccion::factory()->deMarca($concepto, 2)->create(['inspector_id' => $deBaja->id]);
    Sublote::factory()->create(['inspector_id' => $conSublote->id]);
    $usuario = auditorDeRegistro2();

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index'))
        ->assertInertia(fn ($page) => $page
            ->has('inspectores', 3)
            ->where('inspectores.0', ['id' => $activo->id, 'nombre' => 'Ana Activa', 'activo' => true])
            ->where('inspectores.1', ['id' => $deBaja->id, 'nombre' => 'Beto Baja', 'activo' => false])
            ->where('inspectores.2.nombre', 'Ceci Lotes'));

    // El historial de quien ya se fue se sigue pudiendo filtrar.
    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', ['inspector' => $deBaja->id]))
        ->assertInertia(fn ($page) => $page->has('registros.data', 1)->where('filtros.inspector', $deBaja->id));
});

test('de cada sublote se lista su última inspección, con las columnas de accesorios', function () {
    $original = Sublote::factory()->rechazado()->create(['fecha' => '2026-10-01']);
    Sublote::factory()->reinspeccionDe($original)->create(['fecha' => '2026-10-08']);
    Sublote::factory()->rechazado()->create(['lote_id' => $original->lote_id, 'fecha' => '2026-10-20']);
    $usuario = auditorDeRegistro2();

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', ['que' => 'acc', 'sort_by' => 'numero_inspeccion', 'sort_dir' => 'desc']))
        ->assertInertia(fn ($page) => $page
            ->where('registros', null)
            ->has('sublotes.data', 2)
            ->where('conteo.registros', 2)
            ->where('conteo.piezas', null)
            ->where('sublotes.data.0.numero_inspeccion', 2)
            ->where('sublotes.data.0.veredicto', 'aceptado')
            ->where('sublotes.data.0.total_lote', $original->lote->total_unidades)
            ->where('sublotes.data.0.nivel', 'II')
            ->where('sublotes.data.1.sin_disposicion', true));

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', ['que' => 'acc', 'periodo' => 'semana', 'semana' => '2026-W41']))
        ->assertInertia(fn ($page) => $page->has('sublotes.data', 1)->where('sublotes.data.0.numero_inspeccion', 2));
});

test('el estatus no filtra la lista de accesorios', function () {
    Sublote::factory()->create();

    $this->actingAs(auditorDeRegistro2())
        ->get(route('admin.qal.registros2.index', ['que' => 'acc', 'estatus' => 'rechazado']))
        ->assertInertia(fn ($page) => $page->where('filtros.estatus', null)->has('sublotes.data', 1));
});

test('la ficha de una pieza y la de un sublote se abren desde la fila', function () {
    $concepto = Concepto::factory()->create();
    $inspeccion = Inspeccion::factory()->deMarca($concepto)->create();
    $sublote = Sublote::factory()->create();
    $usuario = auditorDeRegistro2();

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', ['ficha' => $inspeccion->id]))
        ->assertInertia(fn ($page) => $page->where('ficha.folio', $inspeccion->folio));

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', ['que' => 'acc', 'sublote' => $sublote->id]))
        ->assertInertia(fn ($page) => $page->where('fichaSublote.id', $sublote->id));

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', ['ficha' => 999999]))
        ->assertNotFound();
});

test('pagina de 50 en 50 y la página siguiente conserva los filtros', function () {
    $concepto = Concepto::factory()->create();
    Inspeccion::factory()->deMarca($concepto)->rechazada()->count(51)->create();

    $this->actingAs(auditorDeRegistro2())
        ->get(route('admin.qal.registros2.index', ['estatus' => 'rechazado', 'periodo' => '7d']))
        ->assertInertia(fn ($page) => $page
            ->has('registros.data', 50)
            ->where('registros.last_page', 2)
            ->where('registros.next_page_url', fn (string $url) => str_contains($url, 'estatus=rechazado') && str_contains($url, 'periodo=7d')));
});

test('exportar es permiso aparte y respeta el periodo', function () {
    Excel::fake();
    $concepto = Concepto::factory()->create();
    Inspeccion::factory()->deMarca($concepto)->create(['fecha' => '2026-10-01']);
    Inspeccion::factory()->deMarca($concepto, 2)->create(['fecha' => '2026-10-20']);
    $original = Sublote::factory()->rechazado()->create(['fecha' => '2026-10-01']);
    Sublote::factory()->reinspeccionDe($original)->create(['fecha' => '2026-10-02']);
    Sublote::factory()->create(['fecha' => '2026-10-20']);

    $this->actingAs(auditorDeRegistro2())
        ->get(route('admin.qal.registros2.exportar'))
        ->assertForbidden();

    $usuario = auditorDeRegistro2(['qal.registros.ver', 'qal.registros.exportar']);

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.exportar', ['periodo' => 'fecha', 'dia' => '2026-10-20']))
        ->assertOk();

    Excel::assertDownloaded(
        'calidad-inspecciones-'.now()->format('Ymd').'.xlsx',
        fn (Registro2InspeccionesExport $export): bool => $export->collection()->count() === 1
            && count($export->collection()->first()) === count($export->headings()),
    );

    // Las reinspecciones salen todas en el Excel, no sólo la vigente.
    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.exportar', ['que' => 'acc', 'periodo' => 'semana', 'semana' => '2026-W40']))
        ->assertOk();

    Excel::assertDownloaded(
        'calidad-sublotes-'.now()->format('Ymd').'.xlsx',
        fn (Registro2SublotesExport $export): bool => $export->collection()->count() === 2
            && $export->collection()->pluck(10)->sort()->values()->all() === ['No', 'Sí'],
    );
});
