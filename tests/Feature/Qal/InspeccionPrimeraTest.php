<?php

use App\Enums\Qal\ResultadoPunto;
use App\Enums\Qal\VeredictoLote;
use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Muestreo;
use App\Models\User;
use Database\Seeders\QalPuntosInspeccionSeeder;
use Spatie\Permission\Models\Permission;

/**
 * Inspección de 1ª: corte y habilitado.
 *
 * La pieza todavía no tiene QR, así que se identifica por la marca de
 * Producción y su consecutivo dentro del lote. Lo que se comprueba es lo que el
 * servidor decide solo: folio y semana, número de inspección, barrenos
 * deducidos y veredicto del muestreo AQL.
 */
beforeEach(fn () => $this->seed(QalPuntosInspeccionSeeder::class));

function inspectorDePrimera(array $permisos = ['qal.inspecciones.crear']): User
{
    foreach ($permisos as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo($permisos);

    return $usuario;
}

/**
 * @return array<string, mixed>
 */
function primeraValida(Concepto $concepto, array $cambios = []): array
{
    return [
        'obra_id' => $concepto->obra_id,
        'fase' => '1ª',
        'subtipo' => 'perfil',
        'fecha' => now()->toDateString(),
        'concepto_id' => $concepto->id,
        'consecutivo' => 1,
        'cantidad_lote' => 3,
        'kg' => 120.5,
        'estatus' => 'liberado',
        'puntos' => ['p1_dim' => 'OK', 'p1_posbar' => 'OK', 'p1_diam' => 'n/a', 'p1_defl' => 'Fuera de tol.'],
        ...$cambios,
    ];
}

test('la captura abre con su permiso y consultar no alcanza para capturar', function () {
    $concepto = Concepto::factory()->create();

    $this->actingAs(inspectorDePrimera())
        ->get(route('admin.qal.formularios'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/calidad/formularios/index'));

    $this->actingAs(inspectorDePrimera(['qal.inspecciones.ver']))
        ->post(route('admin.qal.inspecciones.store'), primeraValida($concepto))
        ->assertForbidden();

    expect(Inspeccion::count())->toBe(0);
});

test('las marcas son las del catalogo vigente de la obra elegida', function () {
    $vigente = Concepto::factory()->create(['marca' => 'SX-CM2-11']);
    $congelado = Catalogo::query()->create([
        'obra_id' => $vigente->obra_id, 'nombre' => 'Versión anterior', 'version' => 0, 'vigente' => false,
    ]);
    Concepto::factory()->create(['obra_id' => $vigente->obra_id, 'catalogo_id' => $congelado->id, 'marca' => 'SX-VIEJA-1']);
    Concepto::factory()->create();

    $this->actingAs(inspectorDePrimera())
        ->get(route('admin.qal.formularios', ['obra' => $vigente->obra_id]))
        ->assertInertia(fn ($page) => $page
            ->where('obraId', $vigente->obra_id)
            ->has('marcas', 1)
            ->where('marcas.0.marca', 'SX-CM2-11'));
});

test('guarda la cabecera con folio y semana y copia la marca como texto', function () {
    $concepto = Concepto::factory()->create(['marca' => 'PJ-CM1-10', 'lote' => 'A']);
    $usuario = inspectorDePrimera();

    $this->actingAs($usuario)
        ->post(route('admin.qal.inspecciones.store'), primeraValida($concepto))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $inspeccion = Inspeccion::sole();

    expect($inspeccion->folio)->toStartWith('QAL-')
        ->and($inspeccion->marca)->toBe('PJ-CM1-10')
        ->and($inspeccion->lote)->toBe('A')
        ->and($inspeccion->catalogo_id)->toBe($concepto->catalogo_id)
        ->and($inspeccion->anio)->toBe(now()->isoWeekYear())
        ->and($inspeccion->semana)->toBe(now()->isoWeek())
        ->and($inspeccion->numero_inspeccion)->toBe(1)
        ->and($inspeccion->capturista_id)->toBe($usuario->id)
        ->and($inspeccion->inspector->usuario_id)->toBe($usuario->id);
});

test('los barrenos se deducen de la posicion y el diametro, no del formulario', function () {
    $concepto = Concepto::factory()->create();

    $this->actingAs(inspectorDePrimera())
        ->post(route('admin.qal.inspecciones.store'), primeraValida($concepto, [
            'puntos' => ['p1_posbar' => 'OK', 'p1_diam' => 'n/a', 'p1_bar' => 'Con defecto', 'p1_defl' => 'Fuera de tol.'],
        ]))
        ->assertSessionHasNoErrors();

    $puntos = Inspeccion::sole()->puntos()->with('punto')->get()->keyBy('punto.clave');

    expect($puntos['p1_bar']->valor_texto)->toBe('OK')
        ->and($puntos['p1_bar']->resultado)->toBe(ResultadoPunto::Ok)
        ->and($puntos['p1_defl']->valor_texto)->toBe('Fuera de tol.')
        ->and($puntos['p1_defl']->resultado)->toBe(ResultadoPunto::NoOk);
});

test('lo que no es del formulario elegido no se guarda', function () {
    $concepto = Concepto::factory()->create();

    // La limpieza es de placa; en perfil se queda en memoria del front si el
    // inspector cambió de subtipo, pero no es parte de esta inspección.
    $this->actingAs(inspectorDePrimera())
        ->post(route('admin.qal.inspecciones.store'), primeraValida($concepto, [
            'puntos' => ['p1_dim' => 'OK', 'p1_limpieza' => 'Con defecto'],
        ]))
        ->assertSessionHasNoErrors();

    expect(Inspeccion::sole()->puntos()->whereRelation('punto', 'clave', 'p1_limpieza')->exists())->toBeFalse();
});

test('reinspeccionar la misma pieza sube el numero de inspeccion', function () {
    $concepto = Concepto::factory()->create();
    $usuario = inspectorDePrimera();

    foreach ([1, 1, 2] as $consecutivo) {
        $this->actingAs($usuario)
            ->post(route('admin.qal.inspecciones.store'), primeraValida($concepto, ['consecutivo' => $consecutivo]))
            ->assertSessionHasNoErrors();
    }

    expect(Inspeccion::query()->orderBy('id')->pluck('numero_inspeccion')->all())->toBe([1, 2, 1])
        ->and(Inspeccion::query()->distinct()->count('folio'))->toBe(3);
});

test('la marca tiene que ser de la obra y el consecutivo no pasa del lote', function () {
    $concepto = Concepto::factory()->create();
    $ajena = Concepto::factory()->create();
    $usuario = inspectorDePrimera();

    $this->actingAs($usuario)
        ->post(route('admin.qal.inspecciones.store'), primeraValida($concepto, ['concepto_id' => $ajena->id]))
        ->assertSessionHasErrors('concepto_id');

    $this->actingAs($usuario)
        ->post(route('admin.qal.inspecciones.store'), primeraValida($concepto, ['consecutivo' => 5, 'cantidad_lote' => 3]))
        ->assertSessionHasErrors('consecutivo');

    expect(Inspeccion::count())->toBe(0);
});

test('una respuesta que no existe en el punto no se guarda', function () {
    $concepto = Concepto::factory()->create();

    $this->actingAs(inspectorDePrimera())
        ->post(route('admin.qal.inspecciones.store'), primeraValida($concepto, ['puntos' => ['p1_dim' => 'Chueca']]))
        ->assertSessionHasErrors('puntos.p1_dim');
});

test('el muestreo guarda el plan vigente y rechaza el lote al alcanzar el rechazo', function () {
    $concepto = Concepto::factory()->create();

    $this->actingAs(inspectorDePrimera())
        ->post(route('admin.qal.inspecciones.store'), primeraValida($concepto, [
            'cantidad_lote' => null,
            'muestreo' => [
                'tamano_lote' => 100,
                'nivel' => 'II',
                'conformes' => 3,
                'rechazadas' => 6,
                'disposicion' => 'Retrabajo completo del lote',
                'detalle_fallas' => "#1 rebaba\n#2 rebaba",
            ],
        ]))
        ->assertSessionHasNoErrors();

    $muestreo = Muestreo::sole();

    expect($muestreo->only(['muestra', 'aceptacion', 'rechazo']))->toBe(['muestra' => 20, 'aceptacion' => 5, 'rechazo' => 6])
        ->and($muestreo->veredicto)->toBe(VeredictoLote::Rechazado)
        ->and($muestreo->disposicion)->toBe('Retrabajo completo del lote')
        // El lote entero es lo que se libera: la cantidad es el lote, no la muestra.
        ->and(Inspeccion::sole()->cantidad_lote)->toBe(100);
});

test('un lote en curso no esta aceptado ni tiene disposicion', function () {
    $concepto = Concepto::factory()->create();

    $this->actingAs(inspectorDePrimera())
        ->post(route('admin.qal.inspecciones.store'), primeraValida($concepto, [
            'muestreo' => [
                'tamano_lote' => 100, 'nivel' => 'II', 'conformes' => 10, 'rechazadas' => 1,
                'disposicion' => 'Liberado bajo concesión',
            ],
        ]))
        ->assertSessionHasNoErrors();

    expect(Muestreo::sole()->veredicto)->toBeNull()
        ->and(Muestreo::sole()->disposicion)->toBeNull();
});

test('no se marcan mas piezas que la muestra', function () {
    $concepto = Concepto::factory()->create();

    $this->actingAs(inspectorDePrimera())
        ->post(route('admin.qal.inspecciones.store'), primeraValida($concepto, [
            'muestreo' => ['tamano_lote' => 8, 'nivel' => 'II', 'conformes' => 3, 'rechazadas' => 0],
        ]))
        ->assertSessionHasErrors('muestreo.conformes');
});

test('las juntas y los espesores no son de primera', function () {
    $concepto = Concepto::factory()->create();

    $this->actingAs(inspectorDePrimera())
        ->post(route('admin.qal.inspecciones.store'), primeraValida($concepto, [
            'juntas' => [['identificador' => 'J1', 'tipo' => 'filete']],
            'pintura' => ['mediciones_visibles' => 5],
        ]))
        ->assertSessionHasErrors(['juntas', 'pintura']);
});
