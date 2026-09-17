<?php

use App\Enums\Qal\EstatusModelo;
use App\Jobs\Qal\ProcesarModeloIfc;
use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Catalogo;
use App\Models\Qal\Junta;
use App\Models\Qal\Modelo;
use App\Models\Qal\ModeloCordon;
use App\Models\Qal\ModeloMarca;
use App\Models\User;
use App\Services\Qal\Ifc\ImportadorDeModelo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

/**
 * Modelos 3D: el IFC de la obra convertido en marcas con sus cordones por el
 * servicio aparte.
 *
 * El servicio se simula con Http::fake: lo que se comprueba es el lado de
 * Laravel —la versión que nace, el job que sube y pregunta sin quedarse
 * esperando, las marcas que se van guardando conforme el servicio las escribe,
 * y que un error del servicio no se esconde—.
 */
beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

function usuarioDeModelos(array $permisos = ['qal.modelos.ver', 'qal.modelos.crear']): User
{
    foreach ($permisos as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo($permisos);

    return $usuario;
}

/**
 * Las marcas que entregaría el servicio: una columna con dos cordones que son
 * UNA junta —lo que en el plano lleva un solo símbolo— y seis barrenos, y una
 * placa sin soldadura ni barrenos.
 */
const MARCAS_DEL_SERVICIO = [
    'SX-CM2-11' => ['file' => 'SX-CM2-11', 'nombre' => 'COLUMNA', 'piezas' => 3, 'peso_kg' => 120.5, 'ensambles' => 4, 'soldaduras' => 2, 'juntas' => 1, 'orificios' => 6, 'soldadura_mm' => 600, 'bbox_mm' => [400, 400, 3000]],
    'SX-XX9-1' => ['file' => 'SX-XX9-1', 'nombre' => 'PLACA', 'piezas' => 1, 'peso_kg' => 5, 'ensambles' => 1, 'soldaduras' => 0, 'juntas' => 0, 'orificios' => 0, 'soldadura_mm' => 0, 'bbox_mm' => [100, 100, 12]],
];

/** El index.json tal como lo va escribiendo el servicio. */
function indiceDelServicio(array $marcas, bool $completo): array
{
    return [
        'completo' => $completo,
        'modelo' => $completo ? 'modelo.glb' : null,
        'welds_version' => '2026-08-21',
        'marcas' => array_intersect_key(MARCAS_DEL_SERVICIO, array_flip($marcas)),
        'totales' => ['marcas' => count($marcas), 'juntas' => 1, 'soldaduras' => 2, 'soldadura_mm' => 600, 'orificios' => 6],
    ];
}

function fichaDelServicio(string $marca): string
{
    $cordon = fn (int $id, bool $remate = false): array => [
        'id' => $id, 'junta_id' => 1, 'remate' => $remate, 'piezas' => ['P001', 'P000'], 'tipo' => 'filete', 'junta' => 'T', 'largo_mm' => 300,
        'ancho_mm' => 8, 'angulo' => 90, 't1_mm' => 8, 't2_mm' => 12, 'cateto_min_mm' => 4.76,
        'cateto_max_mm' => 6.41, 'garganta_min_mm' => 3.37, 'cateto_mm' => null,
        'preparacion' => ['bisel' => 'ninguno'], 'avisos' => [], 'centro' => [0, 0, 0], 'puntos' => [[0, 0, 0], [0.3, 0, 0]],
    ];

    return json_encode([
        'marca' => $marca,
        'soldaduras' => $marca === 'SX-CM2-11' ? [$cordon(1), $cordon(2, remate: true)] : [],
    ]);
}

function modeloConArchivo(array $atributos = []): Modelo
{
    $modelo = Modelo::factory()->pendiente()->create($atributos);
    Storage::disk('local')->put("qal/modelos/{$modelo->id}/modelo.ifc", 'ISO-10303-21;');
    $modelo->update(['archivo_ifc' => "qal/modelos/{$modelo->id}/modelo.ifc"]);

    return $modelo;
}

function correrJob(Modelo $modelo): Modelo
{
    (new ProcesarModeloIfc($modelo->id))->handle(app(ImportadorDeModelo::class));

    return $modelo->fresh();
}

test('subir el ifc crea la version siguiente y la manda a la cola ifc', function () {
    Queue::fake();
    $obra = Obra::factory()->create();
    $usuario = usuarioDeModelos();

    foreach (range(1, 2) as $vuelta) {
        $this->actingAs($usuario)
            ->post(route('admin.prod.modelos.store'), ['obra_id' => $obra->id, 'archivo' => UploadedFile::fake()->create('NAVE.ifc', 500)])
            ->assertSessionHasNoErrors();
    }

    expect(Modelo::query()->orderBy('id')->pluck('version')->all())->toBe([1, 2])
        ->and(Modelo::query()->first()->estatus)->toBe(EstatusModelo::Pendiente);
    Storage::disk('local')->assertExists(Modelo::query()->first()->archivo_ifc);
    Queue::assertPushedOn('ifc', ProcesarModeloIfc::class);
});

test('lo que no es ifc no se sube, y ver no alcanza para subir', function () {
    $obra = Obra::factory()->create();

    $this->actingAs(usuarioDeModelos())
        ->post(route('admin.prod.modelos.store'), ['obra_id' => $obra->id, 'archivo' => UploadedFile::fake()->create('planos.pdf', 50)])
        ->assertSessionHasErrors('archivo');

    $this->actingAs(usuarioDeModelos(['qal.modelos.ver']))
        ->post(route('admin.prod.modelos.store'), ['obra_id' => $obra->id, 'archivo' => UploadedFile::fake()->create('NAVE.ifc', 50)])
        ->assertForbidden();

    expect(Modelo::count())->toBe(0);
});

test('el job sube el archivo, guarda cada marca conforme el servicio la escribe y al final el modelo entero', function () {
    $modelo = modeloConArchivo();
    Concepto::factory()->create(['obra_id' => $modelo->obra_id, 'marca' => 'SX-CM2-11']);
    Http::fake([
        '*/procesar' => Http::response(['id' => 'abc'], 202),
        '*/estado/abc' => Http::sequence()
            ->push(['estado' => 'procesando', 'progreso' => ['marcas_hechas' => 1, 'marcas_total' => 2]])
            ->push(['estado' => 'listo', 'progreso' => ['marcas_hechas' => 2, 'marcas_total' => 2]]),
        '*/resultado/abc/marcas' => Http::sequence()
            ->push(indiceDelServicio(['SX-CM2-11'], completo: false))
            ->push(indiceDelServicio(['SX-CM2-11', 'SX-XX9-1'], completo: true)),
        '*/resultado/abc/marcas/SX-CM2-11.json' => Http::response(fichaDelServicio('SX-CM2-11')),
        '*/resultado/abc/marcas/SX-XX9-1.json' => Http::response(fichaDelServicio('SX-XX9-1')),
        '*/resultado/abc/marcas/*.glb' => Http::response('glTF'),
        '*/resultado/abc/modelo' => Http::response('glTF-completo'),
        '*/trabajos/abc' => Http::response('', 204),
    ]);

    $subido = correrJob($modelo);
    expect($subido->estatus)->toBe(EstatusModelo::Procesando)
        ->and($subido->trabajo_externo_id)->toBe('abc');

    // A media conversión la columna ya está guardada con sus cordones y sirve para el visor.
    $aMedias = correrJob($modelo);
    $columna = ModeloMarca::query()->firstWhere('marca', 'SX-CM2-11');

    expect($aMedias->estatus)->toBe(EstatusModelo::Procesando)
        ->and($aMedias->resumen['progreso'])->toMatchArray(['marcas_hechas' => 1, 'marcas_total' => 2, 'marcas_guardadas' => 1])
        ->and($columna->concepto_id)->not->toBeNull()
        ->and($columna->cordones()->pluck('numero')->all())->toBe([1, 2])
        // Los dos cordones son tramos de la misma junta, y el segundo es el remate.
        ->and($columna->cordones()->pluck('junta_id')->all())->toBe([1, 1])
        ->and($columna->cordones()->pluck('remate')->all())->toBe([false, true])
        ->and($columna->juntas)->toBe(1)
        ->and($columna->orificios)->toBe(6)
        ->and(ModeloMarca::query()->where('marca', 'SX-XX9-1')->exists())->toBeFalse();
    Storage::disk('public')->assertExists("qal/modelos/{$modelo->id}/marks/SX-CM2-11.glb");

    $listo = correrJob($modelo);

    expect($listo->estatus)->toBe(EstatusModelo::Listo)
        ->and($listo->welds_version)->toBe('2026-08-21')
        ->and($listo->archivo_modelo)->toBe('modelo.glb')
        ->and($listo->resumen['marcas'])->toBe(2)
        ->and($listo->resumen['cordones'])->toBe(2)
        ->and($listo->resumen['juntas'])->toBe(1)
        ->and($listo->resumen['orificios'])->toBe(6)
        ->and($listo->resumen['marcas_sin_catalogo'])->toBe(1)
        // La columna no se volvió a bajar: ya estaba.
        ->and(ModeloMarca::query()->firstWhere('marca', 'SX-CM2-11')->id)->toBe($columna->id)
        ->and(ModeloMarca::query()->firstWhere('marca', 'SX-XX9-1')->concepto_id)->toBeNull();

    Storage::disk('public')->assertExists("qal/modelos/{$modelo->id}/modelo.glb");
    expect(Http::recorded(fn ($peticion) => str_ends_with($peticion->url(), '/marcas/SX-CM2-11.json')))->toHaveCount(1);
    Http::assertSent(fn ($peticion) => $peticion->method() === 'DELETE' && str_ends_with($peticion->url(), '/trabajos/abc'));
});

test('si el servicio no puede convertir, el modelo queda en error con su mensaje', function () {
    $modelo = modeloConArchivo(['trabajo_externo_id' => 'abc', 'estatus' => EstatusModelo::Procesando]);
    Http::fake(['*/estado/abc' => Http::response(['estado' => 'error', 'error' => 'RuntimeException: IFC corrupto'])]);

    $fallido = correrJob($modelo);

    expect($fallido->estatus)->toBe(EstatusModelo::Error)
        ->and($fallido->error)->toContain('IFC corrupto');
});

test('si el servicio se reinicio y perdio el trabajo, se pide reprocesar', function () {
    $modelo = modeloConArchivo(['trabajo_externo_id' => 'abc', 'estatus' => EstatusModelo::Procesando]);
    Http::fake(['*/estado/abc' => Http::response(['detail' => 'No existe'], 404)]);

    expect(correrJob($modelo)->error)->toContain('Reprocesa');
});

test('reprocesar crea una version nueva y conserva la anterior con sus cordones', function () {
    Queue::fake();
    $modelo = modeloConArchivo(['estatus' => EstatusModelo::Listo]);
    ModeloCordon::factory()->for(ModeloMarca::factory()->for($modelo, 'modelo'), 'marca')->create();

    $this->actingAs(usuarioDeModelos())
        ->post(route('admin.prod.modelos.reprocesar', $modelo))
        ->assertRedirect();

    $nueva = Modelo::query()->latest('id')->first();

    expect($nueva->version)->toBe(2)
        ->and($nueva->estatus)->toBe(EstatusModelo::Pendiente)
        ->and($modelo->cordones()->count())->toBe(1);
    Storage::disk('local')->assertExists($nueva->archivo_ifc);
    Queue::assertPushedOn('ifc', ProcesarModeloIfc::class);
});

test('un modelo con juntas capturadas sobre sus cordones no se borra', function () {
    $cordon = ModeloCordon::factory()->create();
    Junta::factory()->create(['cordon_id' => $cordon->id]);
    $usuario = usuarioDeModelos(['qal.modelos.eliminar']);

    $this->actingAs($usuario)
        ->delete(route('admin.prod.modelos.destroy', $cordon->marca->modelo))
        ->assertSessionHasErrors('modelo');

    $libre = ModeloCordon::factory()->create()->marca->modelo;
    Storage::disk('public')->put("qal/modelos/{$libre->id}/marks/X.glb", 'glTF');

    $this->actingAs($usuario)
        ->delete(route('admin.prod.modelos.destroy', $libre))
        ->assertRedirect();

    expect(Modelo::query()->pluck('id')->all())->toBe([$cordon->marca->modelo_id]);
    Storage::disk('public')->assertMissing("qal/modelos/{$libre->id}/marks/X.glb");
});

test('las marcas se vuelven a amarrar al catalogo vigente cuando se pide', function () {
    $marca = ModeloMarca::factory()->create(['marca' => 'SX-TP4-2']);
    Concepto::factory()->create(['obra_id' => $marca->modelo->obra_id, 'marca' => 'SX-TP4-2']);

    $this->actingAs(usuarioDeModelos())
        ->post(route('admin.prod.modelos.resolver-marcas', $marca->modelo))
        ->assertRedirect();

    expect($marca->fresh()->concepto_id)->not->toBeNull();
});

test('el modelo es una opcion del catalogo de produccion de la obra', function () {
    $modelo = Modelo::factory()->create();
    Modelo::factory()->create();
    $catalogo = Catalogo::factory()->create(['obra_id' => $modelo->obra_id]);
    $usuario = usuarioDeModelos(['qal.modelos.ver']);

    $this->actingAs($usuario)
        ->get(route('admin.prod.catalogos.modelos', $catalogo))
        ->assertInertia(fn ($page) => $page
            ->component('admin/prod/modelos/index')
            ->where('catalogo.id', $catalogo->id)
            ->has('modelos', 1));

    $this->actingAs($usuario)
        ->get(route('admin.prod.modelos.show', $modelo))
        ->assertInertia(fn ($page) => $page
            ->component('admin/prod/modelos/show')
            ->where('modelo.version', 1)
            ->where('catalogo.id', $catalogo->id));

    $this->actingAs($usuario)
        ->getJson(route('admin.prod.modelos.estado', $modelo))
        ->assertJsonPath('estatus', 'listo');

    $this->actingAs(User::factory()->create())
        ->get(route('admin.prod.catalogos.modelos', $catalogo))
        ->assertForbidden();
});

test('calidad ya no tiene pantalla de modelos, solo la marca para el visor', function () {
    expect(Route::has('admin.qal.modelos.index'))->toBeFalse()
        ->and(Route::has('admin.qal.modelos.show'))->toBeFalse()
        ->and(Route::has('admin.qal.modelos.marca'))->toBeTrue();
});
