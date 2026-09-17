<?php

use App\Enums\Qal\VeredictoLote;
use App\Exports\Qal\InspeccionesExport;
use App\Models\Concepto;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\PuntoInspeccion;
use App\Models\Qal\Sublote;
use App\Models\User;
use Database\Seeders\QalPuntosInspeccionSeeder;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;

/**
 * Registros: la base de lo capturado, para auditar.
 *
 * Lo que se comprueba es lo que el original hacía mal o no hacía: contar
 * registros y piezas por separado, filtrar en el servidor, que la ficha traiga
 * la historia de la pieza, que de los sublotes mande la última inspección y que
 * exportar sea permiso aparte.
 */
function auditorDeCalidad(array $permisos = ['qal.registros.ver']): User
{
    foreach ($permisos as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo($permisos);

    return $usuario;
}

test('registros pide su propio permiso: capturar no alcanza para auditar', function () {
    $this->actingAs(auditorDeCalidad(['qal.inspecciones.crear']))
        ->get(route('admin.qal.registros.index'))
        ->assertForbidden();

    $this->actingAs(auditorDeCalidad())
        ->get(route('admin.qal.registros.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/calidad/registros/index'));
});

test('una pieza reinspeccionada son dos registros y una sola pieza', function () {
    $concepto = Concepto::factory()->create();
    Inspeccion::factory()->deMarca($concepto, 1)->rechazada()->create();
    Inspeccion::factory()->deMarca($concepto, 1)->create(['numero_inspeccion' => 2]);
    Inspeccion::factory()->deMarca($concepto, 2)->create();

    $this->actingAs(auditorDeCalidad())
        ->get(route('admin.qal.registros.index'))
        ->assertInertia(fn ($page) => $page
            ->where('conteo.registros', 3)
            ->where('conteo.piezas', 2)
            ->has('registros.data', 3));
});

test('los filtros se aplican en el servidor', function () {
    $concepto = Concepto::factory()->create(['marca' => 'PJ-CM1-10']);
    Inspeccion::factory()->deMarca($concepto)->rechazada()->create();
    Inspeccion::factory()->deMarca($concepto, 2)->create();
    Inspeccion::factory()->deMarca(Concepto::factory()->create(['marca' => 'TV-TP3-2']))->rechazada()->create();
    $usuario = auditorDeCalidad();

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros.index', ['obra' => $concepto->obra_id]))
        ->assertInertia(fn ($page) => $page->has('registros.data', 2));

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros.index', ['estatus' => 'rechazado', 'buscar' => 'cm1']))
        ->assertInertia(fn ($page) => $page
            ->has('registros.data', 1)
            ->where('registros.data.0.marca', 'PJ-CM1-10'));
});

test('la ficha trae los puntos con su resultado y el historial de la pieza', function () {
    $this->seed(QalPuntosInspeccionSeeder::class);
    $concepto = Concepto::factory()->create();
    $primera = Inspeccion::factory()->deMarca($concepto)->rechazada()->create();
    $primera->puntos()->create([
        'punto_id' => PuntoInspeccion::firstWhere('clave', 'p1_defl')->id,
        'resultado' => 'no_ok',
        'valor_texto' => 'Fuera de tol.',
    ]);
    $segunda = Inspeccion::factory()->deMarca($concepto)->rechazada()->create(['numero_inspeccion' => 2]);
    $usuario = auditorDeCalidad();

    // Desde la primera se ve la segunda, pero no se reinspecciona: la que
    // manda es la última.
    $this->actingAs($usuario)
        ->get(route('admin.qal.registros.index', ['ficha' => $primera->id]))
        ->assertInertia(fn ($page) => $page
            ->where('ficha.folio', $primera->folio)
            ->has('ficha.historial', 2)
            ->where('ficha.puntos.0.puntos.0.etiqueta', 'Deflexión')
            ->where('ficha.puntos.0.puntos.0.resultado', 'no_ok')
            ->where('ficha.puedeReinspeccionar', false));

    $this->actingAs($usuario)
        ->get(route('admin.qal.registros.index', ['ficha' => $segunda->id]))
        ->assertInertia(fn ($page) => $page->where('ficha.puedeReinspeccionar', true));
});

test('de un sublote reinspeccionado se lista sólo su última inspección', function () {
    $original = Sublote::factory()->rechazado()->create();
    Sublote::factory()->reinspeccionDe($original)->create();
    Sublote::factory()->rechazado()->create(['lote_id' => $original->lote_id]);

    $this->actingAs(auditorDeCalidad())
        ->get(route('admin.qal.registros.index', ['que' => 'acc', 'sort_by' => 'numero_inspeccion', 'sort_dir' => 'desc']))
        ->assertInertia(fn ($page) => $page
            ->has('sublotes.data', 2)
            ->where('conteo.registros', 2)
            ->where('sublotes.data.0.numero_inspeccion', 2)
            ->where('sublotes.data.0.veredicto', VeredictoLote::Aceptado->value)
            // El otro sublote se rechazó y nadie decidió qué hacer con él.
            ->where('sublotes.data.1.sin_disposicion', true));
});

test('exportar es permiso aparte y saca lo que filtra la pantalla', function () {
    Excel::fake();
    $concepto = Concepto::factory()->create();
    Inspeccion::factory()->deMarca($concepto)->rechazada()->create();
    Inspeccion::factory()->deMarca($concepto, 2)->create();

    $this->actingAs(auditorDeCalidad())
        ->get(route('admin.qal.registros.exportar'))
        ->assertForbidden();

    $this->actingAs(auditorDeCalidad(['qal.registros.ver', 'qal.registros.exportar']))
        ->get(route('admin.qal.registros.exportar', ['estatus' => 'rechazado']))
        ->assertOk();

    Excel::assertDownloaded(
        'calidad-inspecciones-'.now()->format('Ymd').'.xlsx',
        fn (InspeccionesExport $export): bool => $export->collection()->count() === 1,
    );
});
