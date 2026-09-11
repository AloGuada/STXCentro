<?php

use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use App\Models\Concepto;
use App\Models\Obra as ObraDelPortal;
use App\Models\Prod\Pieza;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Obra;
use App\Models\Qal\Programacion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Avance de producción: el plan de la semana contra lo que calidad inspeccionó.
 *
 * Se comprueba lo que la maqueta ya resolvía, ahora contra la base: el plan se
 * guarda como filas y las bajas llevan motivo; la puerta de 2ª es soldado y la
 * de 3ª es pintura; el arrastre es una lista de piezas que sólo una baja
 * detiene; y las rechazadas van a la cola de reparación sin volver al plan.
 *
 * La semana 34 de 2026 va del 17 al 23 de agosto.
 */
function planeador(array $permisos = ['qal.programacion.ver', 'qal.programacion.capturar']): User
{
    foreach ($permisos as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $usuario = User::factory()->create();
    $usuario->givePermissionTo($permisos);

    return $usuario;
}

/** Una obra de Producción activa y dada de alta en Calidad. */
function obraProgramable(): int
{
    $obra = ObraDelPortal::factory()->create(['activa' => true]);
    Obra::paraObra($obra);

    return $obra->id;
}

/** Una pieza física de la marca, en el catálogo vigente de la obra. */
function piezaDeLaObra(int $obraId, string $marca): Pieza
{
    $concepto = Concepto::query()->where('obra_id', $obraId)->where('marca', $marca)->first()
        ?? Concepto::factory()->create(['obra_id' => $obraId, 'marca' => $marca]);

    return Pieza::factory()->create(['concepto_id' => $concepto->id]);
}

function inspeccionDeAvance(
    Pieza $pieza,
    FaseTransformacion $fase,
    ?Subetapa $subetapa,
    string $fecha,
    EstatusInspeccion $estatus = EstatusInspeccion::Liberado,
): Inspeccion {
    $dia = Carbon::parse($fecha);

    return Inspeccion::factory()->dePieza($pieza, $fase, $subetapa)->create([
        'fecha' => $dia,
        'anio' => $dia->isoWeekYear(),
        'semana' => $dia->isoWeek(),
        'estatus' => $estatus,
    ]);
}

/**
 * @return array<string, mixed>
 */
function planDeLaSemana(int $obraId, string $semana, string $marcas, string $bajas = '', string $fase = '2'): array
{
    return ['obra_id' => $obraId, 'fase' => $fase, 'semana' => $semana, 'marcas' => $marcas, 'bajas' => $bajas];
}

/**
 * Las props de la pantalla tal como llegan.
 *
 * @return array<string, mixed>
 */
function avanceQueVe(TestCase $prueba, User $usuario, array $filtros = []): array
{
    $props = [];

    $prueba->actingAs($usuario)
        ->get(route('admin.qal.avance', $filtros))
        ->assertOk()
        ->assertInertia(function ($page) use (&$props): void {
            $props = $page->toArray()['props'];
        });

    return $props;
}

test('ver el avance y capturar el plan son permisos distintos', function () {
    $obraId = obraProgramable();
    $lector = planeador(['qal.programacion.ver']);

    $this->actingAs($lector)
        ->get(route('admin.qal.avance'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/calidad/avance/index')->where('puedeCapturar', false));

    $this->actingAs($lector)
        ->post(route('admin.qal.avance.programaciones.store'), planDeLaSemana($obraId, '2026-S34', 'PIP-CM1-1'))
        ->assertForbidden();

    expect(Programacion::count())->toBe(0);
});

test('el plan se guarda como filas, con las bajas y su motivo', function () {
    $obraId = obraProgramable();
    $concepto = Concepto::factory()->create(['obra_id' => $obraId, 'marca' => 'PIP-CM1-1']);
    $usuario = planeador();

    $this->actingAs($usuario)
        ->post(route('admin.qal.avance.programaciones.store'), [
            ...planDeLaSemana($obraId, '2026-S34', "PIP-CM1-1\nPIP-TP2-7 x3\npip-tp2-7", 'PIP-TS1-4: cambio de ingeniería'),
            'notas' => 'Falta material para las TS',
        ])
        ->assertSessionHasNoErrors();

    $plan = Programacion::sole();
    $filas = $plan->marcas()->orderBy('id')->get()
        ->map(fn ($fila): array => $fila->only(['marca', 'cantidad', 'es_baja', 'motivo_baja', 'concepto_id']))
        ->all();

    expect($plan->only(['anio', 'semana', 'notas']))->toBe(['anio' => 2026, 'semana' => 34, 'notas' => 'Falta material para las TS'])
        ->and($plan->fase)->toBe(FaseTransformacion::Segunda)
        // La marca repetida se suma, y la que es única en el catálogo se amarra.
        ->and($filas)->toBe([
            ['marca' => 'PIP-CM1-1', 'cantidad' => 1, 'es_baja' => false, 'motivo_baja' => null, 'concepto_id' => $concepto->id],
            ['marca' => 'PIP-TP2-7', 'cantidad' => 4, 'es_baja' => false, 'motivo_baja' => null, 'concepto_id' => null],
            ['marca' => 'PIP-TS1-4', 'cantidad' => 1, 'es_baja' => true, 'motivo_baja' => 'cambio de ingeniería', 'concepto_id' => null],
        ]);

    // Volver a guardar la misma semana reemplaza el plan entero.
    $this->actingAs($usuario)
        ->post(route('admin.qal.avance.programaciones.store'), planDeLaSemana($obraId, '2026-S34', 'PIP-CM1-1'))
        ->assertSessionHasNoErrors();

    expect(Programacion::sole()->marcas()->pluck('marca')->all())->toBe(['PIP-CM1-1']);

    // Y dejarlo vacío lo borra: «sin plan» es un estado que la pantalla enseña.
    $this->actingAs($usuario)
        ->post(route('admin.qal.avance.programaciones.store'), planDeLaSemana($obraId, '2026-S34', ''))
        ->assertSessionHasNoErrors();

    expect(Programacion::count())->toBe(0);
});

test('una baja sin motivo no se guarda', function () {
    $this->actingAs(planeador())
        ->post(route('admin.qal.avance.programaciones.store'), planDeLaSemana(obraProgramable(), '2026-S34', 'PIP-CM1-1', 'PIP-TS1-4'))
        ->assertSessionHasErrors('bajas');

    expect(Programacion::count())->toBe(0);
});

test('la semana cruza el plan con las piezas, y en 2a la puerta es soldado', function () {
    $obraId = obraProgramable();
    $usuario = planeador();

    $this->actingAs($usuario)->post(
        route('admin.qal.avance.programaciones.store'),
        planDeLaSemana($obraId, '2026-S34', "PIP-CM1-1 x2\nPIP-TP2-7\nPIP-TS1-4"),
    );

    inspeccionDeAvance(piezaDeLaObra($obraId, 'PIP-CM1-1'), FaseTransformacion::Segunda, Subetapa::Soldado, '2026-08-19');
    $rechazada = piezaDeLaObra($obraId, 'PIP-CM1-1');
    inspeccionDeAvance($rechazada, FaseTransformacion::Segunda, Subetapa::Soldado, '2026-08-19', EstatusInspeccion::Rechazado);
    // Pasó armado y le falta soldadura: está empezada, no fabricada.
    inspeccionDeAvance(piezaDeLaObra($obraId, 'PIP-TP2-7'), FaseTransformacion::Segunda, Subetapa::ArmadoVestido, '2026-08-19', EstatusInspeccion::Pendiente);

    $vista = avanceQueVe($this, $usuario, ['obra' => $obraId, 'fase' => '2', 'semana' => '2026-S34'])['vista'];

    expect($vista['total'])->toMatchArray([
        'programadas' => 4,
        'fabricadas' => 2,
        'liberadas' => 1,
        'rechazadas' => 1,
        'pendientes' => 2,
        'empezadas' => 1,
        'sinEmpezar' => 1,
        'enReparacion' => 1,
        'cumplimiento' => 50,
        'tasaRechazo' => 50,
        'salieron' => 25,
    ])
        ->and(collect($vista['tipos'])->pluck('programadas', 'tipo')->all())->toBe(['CM' => 2, 'TP' => 1, 'TS' => 1])
        // La rechazada ya está fabricada: va a la cola de reparación, no al plan.
        ->and(collect($vista['reparaciones'])->pluck('qr')->all())->toBe([$rechazada->qr])
        ->and($vista['plan']['marcas'])->toBe("PIP-CM1-1 x2\nPIP-TP2-7\nPIP-TS1-4");
});

test('lo que no se fabrico se arrastra como pieza, y solo una baja lo detiene', function () {
    $obraId = obraProgramable();
    $usuario = planeador();
    $guardar = fn (string $semana, string $marcas, string $bajas = '') => $this->actingAs($usuario)
        ->post(route('admin.qal.avance.programaciones.store'), planDeLaSemana($obraId, $semana, $marcas, $bajas))
        ->assertSessionHasNoErrors();

    $guardar('2026-S33', "PIP-CM1-1\nPIP-CM1-2\nPIP-CM1-3");
    // CM1-3 sí se hizo la semana pasada: no tiene nada que arrastrar.
    inspeccionDeAvance(piezaDeLaObra($obraId, 'PIP-CM1-3'), FaseTransformacion::Segunda, Subetapa::Soldado, '2026-08-12');
    $guardar('2026-S34', 'PIP-TP2-7', 'PIP-CM1-2: cambio de ingeniería');

    $lineas = collect(avanceQueVe($this, $usuario, ['obra' => $obraId, 'semana' => '2026-S34'])['vista']['lineas']);

    // Lo atrasado primero, con la semana de la que viene.
    expect($lineas->map(fn (array $linea): array => [$linea['marca'], $linea['arrastrada'], $linea['desde']])->all())->toBe([
        ['PIP-CM1-1', true, '2026-S33'],
        ['PIP-TP2-7', false, '2026-S34'],
    ]);
});

test('en pintura la puerta es la inspeccion de 3a, y lo soldado sin pintar esta listo para pintar', function () {
    $obraId = obraProgramable();
    $usuario = planeador();
    $pintada = piezaDeLaObra($obraId, 'PIP-CM1-1');
    $sinPintar = piezaDeLaObra($obraId, 'PIP-CM1-1');

    inspeccionDeAvance($pintada, FaseTransformacion::Segunda, Subetapa::Soldado, '2026-08-17');
    inspeccionDeAvance($sinPintar, FaseTransformacion::Segunda, Subetapa::Soldado, '2026-08-17');
    inspeccionDeAvance($pintada, FaseTransformacion::Tercera, null, '2026-08-19');

    $this->actingAs($usuario)->post(
        route('admin.qal.avance.programaciones.store'),
        planDeLaSemana($obraId, '2026-S34', 'PIP-CM1-1 x2', fase: '3'),
    );

    $total = avanceQueVe($this, $usuario, ['obra' => $obraId, 'fase' => '3', 'semana' => '2026-S34'])['vista']['total'];

    expect($total)->toMatchArray(['programadas' => 2, 'fabricadas' => 1, 'liberadas' => 1, 'pendientes' => 1, 'empezadas' => 1]);
});

test('la portada compara las obras sin sumarlas y junta la cola de reparacion', function () {
    $conPlan = obraProgramable();
    $quieta = obraProgramable();
    $usuario = planeador();

    $this->actingAs($usuario)->post(route('admin.qal.avance.programaciones.store'), planDeLaSemana($conPlan, '2026-S34', 'PIP-CM1-1'));
    $rechazada = piezaDeLaObra($conPlan, 'PIP-CM1-1');
    inspeccionDeAvance($rechazada, FaseTransformacion::Segunda, Subetapa::Soldado, '2026-08-19', EstatusInspeccion::Rechazado);

    $comparativa = avanceQueVe($this, $usuario, ['semana' => '2026-S34'])['comparativa'];
    $porObra = collect($comparativa['resumenes'])->keyBy('obra_id');

    expect($porObra[$conPlan]['viva'])->toBeTrue()
        ->and($porObra[$conPlan]['fases']['2'])->toMatchArray(['programadas' => 1, 'fabricadas' => 1, 'enReparacion' => 1])
        ->and($porObra[$quieta]['viva'])->toBeFalse()
        ->and(collect($comparativa['reparaciones'])->pluck('qr')->all())->toBe([$rechazada->qr]);
});
