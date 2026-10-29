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

test('el periodo filtra por rango y se endereza si viene al revés', function () {
    $concepto = Concepto::factory()->create();
    foreach (['2026-10-01', '2026-10-10', '2026-10-20'] as $consecutivo => $fecha) {
        Inspeccion::factory()->deMarca($concepto, $consecutivo + 1)->create(['fecha' => $fecha]);
    }
    $usuario = auditorDeRegistro2();

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', ['desde' => '2026-10-05', 'hasta' => '2026-10-20']))
        ->assertInertia(fn ($page) => $page->has('registros.data', 2)->where('conteo.registros', 2));

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', ['desde' => '2026-10-10']))
        ->assertInertia(fn ($page) => $page->has('registros.data', 2));

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', ['hasta' => '2026-10-01']))
        ->assertInertia(fn ($page) => $page->has('registros.data', 1));

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.index', ['desde' => '2026-10-15', 'hasta' => '2026-10-05']))
        ->assertInertia(fn ($page) => $page
            ->where('filtros.desde', '2026-10-05')
            ->where('filtros.hasta', '2026-10-15')
            ->has('registros.data', 1));
});

test('los valores inválidos se descartan en vez de dejar la tabla vacía', function () {
    $concepto = Concepto::factory()->create();
    Inspeccion::factory()->deMarca($concepto)->create();

    $this->actingAs(auditorDeRegistro2())
        ->get(route('admin.qal.registros2.index', [
            'obra' => 999999,
            'fase' => '9ª',
            'estatus' => 'inventado',
            'desde' => '2026-02-30',
            'hasta' => 'ayer',
            'sort_by' => 'password',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filtros.obra', null)
            ->where('filtros.fase', null)
            ->where('filtros.estatus', null)
            ->where('filtros.desde', null)
            ->where('filtros.hasta', null)
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
        ->get(route('admin.qal.registros2.index', ['que' => 'acc', 'desde' => '2026-10-05', 'hasta' => '2026-10-10']))
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
        ->get(route('admin.qal.registros2.index', ['estatus' => 'rechazado', 'desde' => '2000-01-01']))
        ->assertInertia(fn ($page) => $page
            ->has('registros.data', 50)
            ->where('registros.last_page', 2)
            ->where('registros.next_page_url', fn (string $url) => str_contains($url, 'estatus=rechazado') && str_contains($url, 'desde=2000-01-01')));
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
        ->get(route('admin.qal.registros2.exportar', ['desde' => '2026-10-15']))
        ->assertOk();

    Excel::assertDownloaded(
        'calidad-inspecciones-'.now()->format('Ymd').'.xlsx',
        fn (Registro2InspeccionesExport $export): bool => $export->collection()->count() === 1
            && count($export->collection()->first()) === count($export->headings()),
    );

    // Las reinspecciones salen todas en el Excel, no sólo la vigente.
    $this->actingAs($usuario)
        ->get(route('admin.qal.registros2.exportar', ['que' => 'acc', 'hasta' => '2026-10-05']))
        ->assertOk();

    Excel::assertDownloaded(
        'calidad-sublotes-'.now()->format('Ymd').'.xlsx',
        fn (Registro2SublotesExport $export): bool => $export->collection()->count() === 2
            && $export->collection()->pluck(10)->sort()->values()->all() === ['No', 'Sí'],
    );
});
