<?php

use App\Enums\Qal\AmbitoDefecto;
use App\Enums\Qal\AmbitoPunto;
use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\ResultadoJunta;
use App\Enums\Qal\ResultadoPunto;
use App\Models\Concepto;
use App\Models\Prod\Pieza;
use App\Models\Qal\Defecto;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\InspeccionDefecto;
use App\Models\Qal\Junta;
use App\Models\Qal\PuntoInspeccion;
use App\Models\User;
use Database\Seeders\QalPuntosInspeccionSeeder;
use Spatie\Permission\Models\Permission;

/**
 * Inspección de 2ª: armado, vestido y soldadura, sobre la pieza física que se
 * escaneó.
 *
 * Las reglas que se comprueban son las del formato: armado no es producto
 * terminado, faltar elementos rechaza, soldado pide los elementos del plano, y
 * cada junta del mapeo saca su resultado de sus puntos.
 */
beforeEach(fn () => $this->seed(QalPuntosInspeccionSeeder::class));

function inspectorDeSegunda(): User
{
    Permission::firstOrCreate(['name' => 'qal.inspecciones.crear', 'guard_name' => 'web']);

    $usuario = User::factory()->create();
    $usuario->givePermissionTo('qal.inspecciones.crear');

    return $usuario;
}

/**
 * @return array<string, mixed>
 */
function segundaValida(Pieza $pieza, array $cambios = []): array
{
    return [
        'obra_id' => $pieza->catalogo->obra_id,
        'fase' => '2ª',
        'subetapa' => 'soldado',
        'fecha' => now()->toDateString(),
        'prod_pieza_id' => $pieza->id,
        'kg' => 850,
        'estatus' => 'liberado',
        'puntos' => ['p2_elem' => '11', 'p2_long' => 'OK', 'p2_placas' => 'n/a'],
        ...$cambios,
    ];
}

/**
 * Una junta con los 18 puntos del mapeo en OK.
 *
 * @return array<string, mixed>
 */
function juntaCorrecta(string $identificador, string $tipo = 'ranura', array $cambios = []): array
{
    return [
        'identificador' => $identificador,
        'tipo' => $tipo,
        'puntos' => PuntoInspeccion::query()
            ->where('ambito', AmbitoPunto::Junta->value)
            ->pluck('clave')
            ->mapWithKeys(fn (string $clave): array => [$clave => 'OK'])
            ->all(),
        ...$cambios,
    ];
}

test('la marca se lee de la pieza escaneada y el dimensional se deduce', function () {
    $pieza = Pieza::factory()->create();

    $this->actingAs(inspectorDeSegunda())
        ->post(route('admin.qal.inspecciones.store'), segundaValida($pieza))
        ->assertSessionHasNoErrors();

    $inspeccion = Inspeccion::sole();

    expect($inspeccion->prod_pieza_id)->toBe($pieza->id)
        ->and($inspeccion->concepto_id)->toBe($pieza->concepto_id)
        ->and($inspeccion->marca)->toBe($pieza->marca->marca)
        ->and($inspeccion->qr)->toBe($pieza->qr)
        ->and($inspeccion->puntos()->whereRelation('punto', 'clave', 'p2_dimok')->value('valor_texto'))->toBe('OK');
});

test('en armado la pieza no se libera', function () {
    $pieza = Pieza::factory()->create();

    $this->actingAs(inspectorDeSegunda())
        ->post(route('admin.qal.inspecciones.store'), segundaValida($pieza, ['subetapa' => 'armado_vestido', 'puntos' => []]))
        ->assertSessionHasErrors('estatus');
});

test('faltar elementos por vestir rechaza la pieza sola', function () {
    $pieza = Pieza::factory()->create();

    $this->actingAs(inspectorDeSegunda())
        ->post(route('admin.qal.inspecciones.store'), segundaValida($pieza, [
            'subetapa' => 'armado_vestido',
            'estatus' => 'pendiente',
            'puntos' => ['p2_faltavest' => '2'],
        ]))
        ->assertSessionHasNoErrors();

    expect(Inspeccion::sole()->estatus)->toBe(EstatusInspeccion::Rechazado);
});

test('soldado exige el numero de elementos del plano', function () {
    $pieza = Pieza::factory()->create();

    $this->actingAs(inspectorDeSegunda())
        ->post(route('admin.qal.inspecciones.store'), segundaValida($pieza, ['puntos' => ['p2_long' => 'OK']]))
        ->assertSessionHasErrors('puntos.p2_elem');
});

test('un filete bajo el nominal marca el perfil y deja la junta con defecto', function () {
    $pieza = Pieza::factory()->create();

    $this->actingAs(inspectorDeSegunda())
        ->post(route('admin.qal.inspecciones.store'), segundaValida($pieza, ['juntas' => [
            juntaCorrecta('j1', 'filete', ['espesor_requerido_mm' => 8, 'espesor_medido_mm' => 7.5]),
            // La ranura no lleva medida: lo que mande el formulario se descarta.
            juntaCorrecta('J2', 'ranura', ['espesor_requerido_mm' => 8, 'espesor_medido_mm' => 7.5]),
        ]]))
        ->assertSessionHasNoErrors();

    $filete = Junta::firstWhere('identificador', 'J1');
    $ranura = Junta::firstWhere('identificador', 'J2');

    expect($filete->espesor_cumple)->toBeFalse()
        ->and($filete->resultado)->toBe(ResultadoJunta::ConDefecto)
        ->and($filete->puntos()->whereRelation('punto', 'clave', 'm_perfil')->value('resultado'))->toBe(ResultadoPunto::NoOk)
        ->and($ranura->resultado)->toBe(ResultadoJunta::Correcta)
        ->and($ranura->espesor_medido_mm)->toBeNull();
});

test('la misma junta reinspeccionada sube su intento', function () {
    $pieza = Pieza::factory()->create();
    $usuario = inspectorDeSegunda();

    foreach (range(1, 2) as $vuelta) {
        $this->actingAs($usuario)
            ->post(route('admin.qal.inspecciones.store'), segundaValida($pieza, ['juntas' => [juntaCorrecta('J1')]]))
            ->assertSessionHasNoErrors();
    }

    expect(Junta::query()->orderBy('id')->pluck('intento')->all())->toBe([1, 2])
        ->and(Inspeccion::query()->orderBy('id')->pluck('numero_inspeccion')->all())->toBe([1, 2]);
});

test('la pieza tiene que ser de la obra elegida', function () {
    $pieza = Pieza::factory()->create();
    $otra = Concepto::factory()->create();

    $this->actingAs(inspectorDeSegunda())
        ->post(route('admin.qal.inspecciones.store'), segundaValida($pieza, ['obra_id' => $otra->obra_id]))
        ->assertSessionHasErrors('prod_pieza_id');
});

test('los defectos de soldadura van con su cantidad y los de pintura no entran', function () {
    $pieza = Pieza::factory()->create();
    $usuario = inspectorDeSegunda();
    $soldadura = Defecto::factory()->deAmbito(AmbitoDefecto::Soldadura)->create();
    $pintura = Defecto::factory()->deAmbito(AmbitoDefecto::Pintura)->create();

    $this->actingAs($usuario)
        ->post(route('admin.qal.inspecciones.store'), segundaValida($pieza, [
            'defectos' => [['defecto_id' => $pintura->id, 'cantidad' => 1]],
        ]))
        ->assertSessionHasErrors('defectos.0.defecto_id');

    $this->actingAs($usuario)
        ->post(route('admin.qal.inspecciones.store'), segundaValida($pieza, [
            'defectos' => [['defecto_id' => $soldadura->id, 'cantidad' => 3]],
        ]))
        ->assertSessionHasNoErrors();

    expect(InspeccionDefecto::sole()->cantidad)->toBe(3);
});

test('armado no mapea juntas', function () {
    $pieza = Pieza::factory()->create();

    $this->actingAs(inspectorDeSegunda())
        ->post(route('admin.qal.inspecciones.store'), segundaValida($pieza, [
            'subetapa' => 'armado_vestido',
            'estatus' => 'pendiente',
            'puntos' => [],
            'juntas' => [juntaCorrecta('J1')],
        ]))
        ->assertSessionHasErrors('juntas');
});
