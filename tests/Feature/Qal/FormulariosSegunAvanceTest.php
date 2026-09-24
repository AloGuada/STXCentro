<?php

use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use App\Models\Concepto;
use App\Models\Prod\Pieza;
use App\Models\Qal\ConfiguracionQal;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Programacion;
use App\Models\Qal\ProgramacionPieza;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Seeders\QalPuntosInspeccionSeeder;
use Spatie\Permission\Models\Permission;

/**
 * Formularios limitado al avance de producción: con el interruptor encendido
 * sólo se escanea, se registra y se ve en Registros lo que Producción
 * programó —plan cerrado de la semana, lo atrasado y lo que ya tiene
 * inspección en esa fase—, y sin el plan de la semana cerrado no entra nada
 * de esa fase.
 */
beforeEach(fn () => $this->seed(QalPuntosInspeccionSeeder::class));

function inspectorConFiltro(array $permisos = ['qal.inspecciones.crear']): User
{
    $usuario = User::factory()->create();

    foreach ($permisos as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        $usuario->givePermissionTo($permiso);
    }

    return $usuario;
}

function encenderFiltroPorAvance(): void
{
    ConfiguracionQal::actual()->update(['formularios_segun_avance' => true]);
}

/** Mete la pieza al plan de esa fase y semana de su obra (cerrado, salvo que se diga). */
function programarPieza(Pieza $pieza, FaseTransformacion $fase = FaseTransformacion::Segunda, ?CarbonInterface $semana = null, bool $cerrado = true): Programacion
{
    $semana ??= now();

    $plan = Programacion::query()->firstOrCreate([
        'obra_id' => $pieza->catalogo->obra_id,
        'fase' => $fase,
        'anio' => $semana->isoWeekYear(),
        'semana' => $semana->isoWeek(),
    ], ['cerrada_at' => $cerrado ? now() : null]);

    ProgramacionPieza::factory()->create([
        'programacion_id' => $plan->id,
        'pieza_id' => $pieza->id,
        'concepto_id' => $pieza->concepto_id,
        'qr' => $pieza->qr,
    ]);

    return $plan;
}

/** Un plan cerrado de esta semana con otra pieza: la obra sí tiene plan. */
function planCerradoConOtraPieza(Pieza $deLaObra, FaseTransformacion $fase = FaseTransformacion::Segunda): void
{
    programarPieza(Pieza::factory()->create(['concepto_id' => $deLaObra->concepto_id]), $fase);
}

function resolver(Pieza $pieza, string $fase = '2ª'): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(inspectorConFiltro())
        ->getJson(route('admin.qal.piezas.resolver', ['codigo' => $pieza->qr, 'fase' => $fase]));
}

/**
 * @return array<string, mixed>
 */
function soldadoDe(Pieza $pieza): array
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
    ];
}

describe('escanear', function () {
    test('apagado se escanea cualquier pieza, aunque no haya plan', function () {
        resolver(Pieza::factory()->create())->assertOk();
    });

    test('encendido entra la pieza del plan cerrado de la semana y no la que no se programo', function () {
        encenderFiltroPorAvance();
        $programada = Pieza::factory()->create();
        $fuera = Pieza::factory()->create(['concepto_id' => $programada->concepto_id]);
        programarPieza($programada);

        resolver($programada)->assertOk()->assertJsonPath('id', $programada->id);
        resolver($fuera)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta pieza no está en el avance de producción de armado y soldado (2ª): no fue programada en un plan cerrado ni tiene inspecciones en esa fase.');
    });

    test('sin el plan de la semana cerrado no entra nada, ni lo que esta en el borrador', function () {
        encenderFiltroPorAvance();
        $pieza = Pieza::factory()->create();
        programarPieza($pieza, cerrado: false);

        resolver($pieza)
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $mensaje): bool => str_contains($mensaje, 'no ha cerrado el plan'));
    });

    test('lo atrasado de un plan cerrado anterior sigue entrando', function () {
        encenderFiltroPorAvance();
        $atrasada = Pieza::factory()->create();
        programarPieza($atrasada, semana: now()->subWeeks(2));
        planCerradoConOtraPieza($atrasada);

        resolver($atrasada)->assertOk();
    });

    test('lo programado para una semana futura todavia no entra', function () {
        encenderFiltroPorAvance();
        $adelantada = Pieza::factory()->create();
        programarPieza($adelantada, semana: now()->addWeek());
        planCerradoConOtraPieza($adelantada);

        resolver($adelantada)->assertStatus(422);
    });

    test('la pieza con inspeccion en esa fase entra aunque ya no este en el plan', function () {
        encenderFiltroPorAvance();
        $rechazada = Pieza::factory()->create();
        Inspeccion::factory()->dePieza($rechazada, FaseTransformacion::Segunda, Subetapa::Soldado)
            ->create(['estatus' => EstatusInspeccion::Rechazado]);
        planCerradoConOtraPieza($rechazada);

        resolver($rechazada)->assertOk();
    });

    test('cada fase se mide contra su propio plan', function () {
        encenderFiltroPorAvance();
        $pieza = Pieza::factory()->create();
        programarPieza($pieza, FaseTransformacion::Segunda);

        resolver($pieza, '2ª')->assertOk();
        // Programada en 2ª, pero la obra no tiene plan de pintura cerrado.
        resolver($pieza, '3ª')->assertStatus(422);

        programarPieza($pieza, FaseTransformacion::Tercera);

        resolver($pieza, '3ª')->assertOk();
    });
});

describe('registrar', function () {
    test('encendido el servidor se niega a guardar una pieza fuera del avance', function () {
        encenderFiltroPorAvance();
        $fuera = Pieza::factory()->create();
        planCerradoConOtraPieza($fuera);

        $this->actingAs(inspectorConFiltro())
            ->post(route('admin.qal.inspecciones.store'), soldadoDe($fuera))
            ->assertSessionHasErrors('prod_pieza_id');

        expect(Inspeccion::count())->toBe(0);
    });

    test('encendido se guarda la pieza programada', function () {
        encenderFiltroPorAvance();
        $programada = Pieza::factory()->create();
        programarPieza($programada);

        $this->actingAs(inspectorConFiltro())
            ->post(route('admin.qal.inspecciones.store'), soldadoDe($programada))
            ->assertSessionHasNoErrors();

        expect(Inspeccion::sole()->prod_pieza_id)->toBe($programada->id);
    });

    test('apagado se guarda cualquier pieza, como siempre', function () {
        $this->actingAs(inspectorConFiltro())
            ->post(route('admin.qal.inspecciones.store'), soldadoDe(Pieza::factory()->create()))
            ->assertSessionHasNoErrors();

        expect(Inspeccion::count())->toBe(1);
    });
});

describe('pestana registros', function () {
    test('encendido solo salen las marcas y piezas habilitadas', function () {
        encenderFiltroPorAvance();
        $marca = Concepto::factory()->create(['marca' => 'SX-CM1-1']);
        [$programada, $fuera] = Pieza::factory()->count(2)->create(['concepto_id' => $marca->id]);
        $sinPlan = Concepto::factory()->create(['obra_id' => $marca->obra_id, 'catalogo_id' => $marca->catalogo_id, 'marca' => 'SX-TA1-2']);
        Pieza::factory()->create(['concepto_id' => $sinPlan->id]);
        programarPieza($programada);

        $this->actingAs(inspectorConFiltro())
            ->get(route('admin.qal.formularios', ['obra' => $marca->obra_id, 'marca' => $marca->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('avance', 1)
                ->where('avance.0.marca', 'SX-CM1-1')
                ->where('avance.0.piezas', 1)
                ->has('piezasDeMarca.piezas', 1)
                ->where('piezasDeMarca.piezas.0.qr', $programada->qr)
                ->where('filtroAvance.obra.fases.2ª', true)
                ->where('filtroAvance.obra.fases.3ª', false));

        expect($fuera->qr)->not->toBe($programada->qr);
    });

    test('apagado salen todas y no hay aviso', function () {
        $marca = Concepto::factory()->create();
        Pieza::factory()->count(3)->create(['concepto_id' => $marca->id]);

        $this->actingAs(inspectorConFiltro())
            ->get(route('admin.qal.formularios', ['obra' => $marca->obra_id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('avance.0.piezas', 3)
                ->where('filtroAvance', null));
    });
});

describe('configuracion', function () {
    test('una instalacion nueva arranca con el filtro encendido', function () {
        ConfiguracionQal::query()->delete();

        expect(ConfiguracionQal::actual()->formularios_segun_avance)->toBeTrue();
    });

    test('quien tiene el permiso enciende y apaga el filtro', function () {
        $admin = inspectorConFiltro(['qal.configuracion.editar']);

        $this->actingAs($admin)
            ->get(route('admin.qal.configuracion.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('configuracion.formularios_segun_avance', false));

        $this->actingAs($admin)
            ->put(route('admin.qal.configuracion.update'), ['formularios_segun_avance' => true])
            ->assertSessionHasNoErrors();

        expect(ConfiguracionQal::actual()->formularios_segun_avance)->toBeTrue();
    });

    test('sin el permiso no se entra ni se cambia', function () {
        $inspector = inspectorConFiltro();

        $this->actingAs($inspector)->get(route('admin.qal.configuracion.edit'))->assertForbidden();
        $this->actingAs($inspector)
            ->put(route('admin.qal.configuracion.update'), ['formularios_segun_avance' => true])
            ->assertForbidden();

        expect(ConfiguracionQal::actual()->formularios_segun_avance)->toBeFalse();
    });
});
