<?php

use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use App\Models\Concepto;
use App\Models\Obra as ObraDelPortal;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Pieza;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Obra;
use App\Models\Qal\Programacion;
use App\Models\Qal\ProgramacionPieza;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Avance de producción: el plan de la semana contra lo que calidad inspeccionó.
 *
 * El plan va pieza por pieza —el QR, el grupo de trabajo que lo hace y su
 * módulo— y nace abierto: un borrador que no cuenta ni ve Calidad hasta que
 * se cierra, y que cerrado ya no se toca; la puerta de 2ª es soldado y la de 3ª
 * es pintura; el arrastre es una lista de piezas de planes cerrados que sólo
 * se detiene fabricándolas; y las rechazadas van a la cola de
 * reparación sin volver al plan.
 *
 * La semana 34 de 2026 va del 17 al 23 de agosto.
 */
function planeador(array $permisos = ['qal.programacion.ver', 'qal.programacion.capturar', 'qal.programacion.cerrar']): User
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
 * Lo que manda el formulario «agregar al plan».
 *
 * @param  list<Pieza>  $piezas
 * @return array<string, mixed>
 */
function piezasParaElPlan(int $obraId, string $semana, array $piezas, ?GrupoTrabajo $grupo = null, string $fase = '2', ?string $modulo = '1.2'): array
{
    $grupo ??= GrupoTrabajo::factory()->create();

    return [
        'obra_id' => $obraId,
        'fase' => $fase,
        'semana' => $semana,
        'piezas' => array_map(fn (Pieza $pieza): int => $pieza->id, $piezas),
        'grupo_trabajo_id' => $grupo->id,
        'modulo' => $modulo,
    ];
}

/** Agrega las piezas al plan de la semana y lo cierra, que es cuando cuenta. */
function planCerrado(TestCase $prueba, User $usuario, int $obraId, string $semana, array $piezas, ?GrupoTrabajo $grupo = null, string $fase = '2'): void
{
    $prueba->actingAs($usuario)
        ->post(route('admin.qal.avance.plan.store'), piezasParaElPlan($obraId, $semana, $piezas, $grupo, $fase))
        ->assertSessionHasNoErrors();

    $prueba->actingAs($usuario)
        ->post(route('admin.qal.avance.programaciones.cerrar'), ['obra_id' => $obraId, 'fase' => $fase, 'semana' => $semana])
        ->assertSessionHasNoErrors();
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
        ->post(route('admin.qal.avance.plan.store'), piezasParaElPlan($obraId, '2026-S34', [piezaDeLaObra($obraId, 'PIP-CM1-1')]))
        ->assertForbidden();

    $this->actingAs($lector)
        ->post(route('admin.qal.avance.programaciones.store'), ['obra_id' => $obraId, 'fase' => '2', 'semana' => '2026-S34', 'notas' => 'x'])
        ->assertForbidden();

    expect(Programacion::count())->toBe(0);
});

test('las piezas se agregan al plan con su grupo y su modulo, y lo suyo queda congelado', function () {
    $obraId = obraProgramable();
    $usuario = planeador();
    $grupo = GrupoTrabajo::factory()->create();
    $concepto = Concepto::factory()->create(['obra_id' => $obraId, 'marca' => 'PIP-CM1-1', 'lote' => 'L2']);
    $primera = Pieza::factory()->create(['concepto_id' => $concepto->id, 'qr' => '155001', 'qs' => '1']);
    $segunda = Pieza::factory()->create(['concepto_id' => $concepto->id, 'qr' => '155002', 'qs' => '2']);

    $this->actingAs($usuario)
        ->post(route('admin.qal.avance.plan.store'), piezasParaElPlan($obraId, '2026-S34', [$primera, $segunda], $grupo, modulo: ' 3.1 '))
        ->assertSessionHasNoErrors();

    $plan = Programacion::sole();

    expect($plan->only(['anio', 'semana']))->toBe(['anio' => 2026, 'semana' => 34])
        ->and($plan->fase)->toBe(FaseTransformacion::Segunda)
        ->and($plan->piezas()->orderBy('id')->get()->map(fn (ProgramacionPieza $fila): array => $fila->only(['pieza_id', 'marca', 'lote', 'qr', 'qs', 'grupo_trabajo_id']))->all())->toBe([
            ['pieza_id' => $primera->id, 'marca' => 'PIP-CM1-1', 'lote' => 'L2', 'qr' => '155001', 'qs' => '1', 'grupo_trabajo_id' => $grupo->id],
            ['pieza_id' => $segunda->id, 'marca' => 'PIP-CM1-1', 'lote' => 'L2', 'qr' => '155002', 'qs' => '2', 'grupo_trabajo_id' => $grupo->id],
        ]);

    // Agregar otra vez la misma pieza no la repite.
    $this->actingAs($usuario)
        ->post(route('admin.qal.avance.plan.store'), piezasParaElPlan($obraId, '2026-S34', [$primera], $grupo))
        ->assertSessionHasNoErrors();

    expect(ProgramacionPieza::count())->toBe(2);

    $linea = avanceQueVe($this, $usuario, ['obra' => $obraId, 'semana' => '2026-S34'])['vista']['borrador'][0];

    expect($linea)->toMatchArray(['marca' => 'PIP-CM1-1', 'lote' => 'L2', 'qr' => '155001', 'grupo' => $grupo->descripcion, 'modulo' => '3.1']);
});

test('las piezas son de la obra del plan, llevan grupo y el modulo es opcional', function () {
    $obraId = obraProgramable();
    $usuario = planeador();
    $pieza = piezaDeLaObra($obraId, 'PIP-CM1-1');

    $this->actingAs($usuario)
        ->post(route('admin.qal.avance.plan.store'), piezasParaElPlan($obraId, '2026-S34', [piezaDeLaObra(obraProgramable(), 'PIP-CM1-1')]))
        ->assertSessionHasErrors('piezas');

    $this->actingAs($usuario)
        ->post(route('admin.qal.avance.plan.store'), [...piezasParaElPlan($obraId, '2026-S34', [$pieza]), 'grupo_trabajo_id' => null])
        ->assertSessionHasErrors('grupo_trabajo_id');

    expect(Programacion::count())->toBe(0);

    $this->actingAs($usuario)
        ->post(route('admin.qal.avance.plan.store'), piezasParaElPlan($obraId, '2026-S34', [$pieza], modulo: null))
        ->assertSessionHasNoErrors();

    expect(ProgramacionPieza::sole()->modulo)->toBeNull();
});

test('una pieza se quita del plan, y el plan vacio se borra', function () {
    $obraId = obraProgramable();
    $usuario = planeador();

    $this->actingAs($usuario)->post(route('admin.qal.avance.plan.store'), piezasParaElPlan($obraId, '2026-S34', [piezaDeLaObra($obraId, 'PIP-CM1-1')]));

    $this->actingAs(planeador(['qal.programacion.ver']))
        ->delete(route('admin.qal.avance.plan.destroy', ProgramacionPieza::sole()))
        ->assertForbidden();

    $this->actingAs($usuario)
        ->delete(route('admin.qal.avance.plan.destroy', ProgramacionPieza::sole()))
        ->assertSessionHasNoErrors();

    expect(ProgramacionPieza::count())->toBe(0)
        ->and(Programacion::count())->toBe(0);
});

test('las notas de la semana se guardan aparte de las piezas', function () {
    $obraId = obraProgramable();
    $usuario = planeador();
    $notas = fn (?string $texto) => $this->actingAs($usuario)
        ->post(route('admin.qal.avance.programaciones.store'), ['obra_id' => $obraId, 'fase' => '2', 'semana' => '2026-S34', 'notas' => $texto])
        ->assertSessionHasNoErrors();

    $notas('Falta material para las TS');

    expect(Programacion::sole()->notas)->toBe('Falta material para las TS')
        ->and(avanceQueVe($this, $usuario, ['obra' => $obraId, 'semana' => '2026-S34'])['vista']['plan'])->toMatchArray(['estado' => 'abierto', 'notas' => 'Falta material para las TS']);

    // Sin notas ni piezas no queda plan: «sin plan» es un estado que la pantalla enseña.
    $notas(null);

    expect(Programacion::count())->toBe(0);
});

test('el plan abierto es un borrador: no cuenta ni lo ve calidad hasta que se cierra', function () {
    $obraId = obraProgramable();
    $usuario = planeador();
    $calidad = planeador(['qal.programacion.ver']);
    $pieza = piezaDeLaObra($obraId, 'PIP-CM1-1');
    $filtros = ['obra' => $obraId, 'semana' => '2026-S34'];

    $this->actingAs($usuario)->post(route('admin.qal.avance.plan.store'), piezasParaElPlan($obraId, '2026-S34', [$pieza]));
    $this->actingAs($usuario)->post(route('admin.qal.avance.programaciones.store'), ['obra_id' => $obraId, 'fase' => '2', 'semana' => '2026-S34', 'notas' => 'en armado']);

    $deQuienCaptura = avanceQueVe($this, $usuario, $filtros)['vista'];
    $deCalidad = avanceQueVe($this, $calidad, $filtros)['vista'];

    expect($deQuienCaptura['plan']['estado'])->toBe('abierto')
        ->and(collect($deQuienCaptura['borrador'])->pluck('qr')->all())->toBe([$pieza->qr])
        ->and($deQuienCaptura['lineas'])->toBe([])
        ->and($deQuienCaptura['total']['programadas'])->toBe(0)
        // Calidad sabe que hay un plan en armado, pero no ve ni sus piezas ni sus notas.
        ->and($deCalidad['plan'])->toMatchArray(['estado' => 'abierto', 'notas' => null])
        ->and($deCalidad['borrador'])->toBeNull()
        ->and($deCalidad['lineas'])->toBe([])
        ->and(avanceQueVe($this, $calidad, ['semana' => '2026-S34'])['comparativa']['resumenes'][0]['fases']['2']['programadas'])->toBe(0);

    $this->actingAs($usuario)
        ->post(route('admin.qal.avance.programaciones.cerrar'), ['obra_id' => $obraId, 'fase' => '2', 'semana' => '2026-S34'])
        ->assertSessionHasNoErrors();

    $cerrado = avanceQueVe($this, $calidad, $filtros)['vista'];

    expect($cerrado['plan'])->toMatchArray(['estado' => 'cerrado', 'notas' => 'en armado', 'cerradoPor' => $usuario->name])
        ->and($cerrado['borrador'])->toBeNull()
        ->and(collect($cerrado['lineas'])->pluck('qr')->all())->toBe([$pieza->qr])
        ->and($cerrado['total']['programadas'])->toBe(1);
});

test('cerrar el plan es un permiso aparte, y no se cierra un plan sin piezas', function () {
    $obraId = obraProgramable();
    $cierre = ['obra_id' => $obraId, 'fase' => '2', 'semana' => '2026-S34'];

    $this->actingAs(planeador())
        ->post(route('admin.qal.avance.programaciones.cerrar'), $cierre)
        ->assertSessionHasErrors('plan');

    $capturista = planeador(['qal.programacion.ver', 'qal.programacion.capturar']);
    $this->actingAs($capturista)->post(route('admin.qal.avance.plan.store'), piezasParaElPlan($obraId, '2026-S34', [piezaDeLaObra($obraId, 'PIP-CM1-1')]));

    $this->actingAs($capturista)
        ->post(route('admin.qal.avance.programaciones.cerrar'), $cierre)
        ->assertForbidden();

    expect(Programacion::sole()->cerrada())->toBeFalse();
});

test('el plan cerrado ya no se toca: ni se le agregan piezas ni se le quitan', function () {
    $obraId = obraProgramable();
    $usuario = planeador();
    $cierre = ['obra_id' => $obraId, 'fase' => '2', 'semana' => '2026-S34'];

    planCerrado($this, $usuario, $obraId, '2026-S34', [piezaDeLaObra($obraId, 'PIP-CM1-1')]);

    $this->actingAs($usuario)
        ->post(route('admin.qal.avance.plan.store'), piezasParaElPlan($obraId, '2026-S34', [piezaDeLaObra($obraId, 'PIP-CM1-2')]))
        ->assertSessionHasErrors('piezas');

    $this->actingAs($usuario)
        ->delete(route('admin.qal.avance.plan.destroy', ProgramacionPieza::sole()))
        ->assertSessionHasErrors('plan');

    $this->actingAs($usuario)
        ->post(route('admin.qal.avance.programaciones.cerrar'), $cierre)
        ->assertSessionHasErrors('plan');

    expect(ProgramacionPieza::count())->toBe(1);
});

test('la semana cruza el plan con las piezas, y en 2a la puerta es soldado', function () {
    $obraId = obraProgramable();
    $usuario = planeador();
    $liberada = piezaDeLaObra($obraId, 'PIP-CM1-1');
    $rechazada = piezaDeLaObra($obraId, 'PIP-CM1-1');
    $empezada = piezaDeLaObra($obraId, 'PIP-TP2-7');
    $sinEmpezar = piezaDeLaObra($obraId, 'PIP-TS1-4');

    planCerrado($this, $usuario, $obraId, '2026-S34', [$liberada, $rechazada, $empezada, $sinEmpezar]);

    inspeccionDeAvance($liberada, FaseTransformacion::Segunda, Subetapa::Soldado, '2026-08-19');
    inspeccionDeAvance($rechazada, FaseTransformacion::Segunda, Subetapa::Soldado, '2026-08-19', EstatusInspeccion::Rechazado);
    // Pasó armado y le falta soldadura: está empezada, no fabricada.
    inspeccionDeAvance($empezada, FaseTransformacion::Segunda, Subetapa::ArmadoVestido, '2026-08-19', EstatusInspeccion::Pendiente);
    // Otra pieza de la marca, fabricada pero fuera del plan: no cuenta.
    inspeccionDeAvance(piezaDeLaObra($obraId, 'PIP-TS1-4'), FaseTransformacion::Segunda, Subetapa::Soldado, '2026-08-19');

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
        ->and(collect($vista['reparaciones'])->pluck('qr')->all())->toBe([$rechazada->qr]);
});

test('lo que no se fabrico de un plan cerrado se arrastra como pieza hasta que se fabrique', function () {
    $obraId = obraProgramable();
    $usuario = planeador();
    $grupo = GrupoTrabajo::factory()->create();

    $pendiente = piezaDeLaObra($obraId, 'PIP-CM1-1');
    $reprogramada = piezaDeLaObra($obraId, 'PIP-CM1-2');
    $hecha = piezaDeLaObra($obraId, 'PIP-CM1-3');
    $nueva = piezaDeLaObra($obraId, 'PIP-TP2-7');

    planCerrado($this, $usuario, $obraId, '2026-S33', [$pendiente, $reprogramada, $hecha], $grupo);
    // CM1-3 sí se hizo la semana pasada: no tiene nada que arrastrar.
    inspeccionDeAvance($hecha, FaseTransformacion::Segunda, Subetapa::Soldado, '2026-08-12');

    // Lo pendiente de un plan cerrado se puede volver a programar…
    $this->actingAs($usuario)
        ->getJson(route('admin.qal.avance.piezas', ['marca' => $reprogramada->concepto_id, 'fase' => '2', 'semana' => '2026-S34']))
        ->assertJsonPath('0.estado', 'pendiente');

    // …y entonces es de esta semana, no arrastrada.
    planCerrado($this, $usuario, $obraId, '2026-S34', [$nueva, $reprogramada], $grupo);

    $lineas = collect(avanceQueVe($this, $usuario, ['obra' => $obraId, 'semana' => '2026-S34'])['vista']['lineas']);

    // Lo atrasado primero, con la semana de la que viene y el grupo que lo tenía.
    expect($lineas->map(fn (array $linea): array => [$linea['qr'], $linea['arrastrada'], $linea['desde'], $linea['grupo']])->all())->toBe([
        [$pendiente->qr, true, '2026-S33', $grupo->descripcion],
        [$reprogramada->qr, false, '2026-S34', $grupo->descripcion],
        [$nueva->qr, false, '2026-S34', $grupo->descripcion],
    ]);

    // Al fabricarse deja de arrastrarse.
    inspeccionDeAvance($pendiente, FaseTransformacion::Segunda, Subetapa::Soldado, '2026-08-19');

    expect(collect(avanceQueVe($this, $usuario, ['obra' => $obraId, 'semana' => '2026-S35'])['vista']['lineas'])->pluck('qr')->all())
        ->toBe([$reprogramada->qr, $nueva->qr]);
});

test('en pintura la puerta es la inspeccion de 3a, y lo soldado sin pintar esta listo para pintar', function () {
    $obraId = obraProgramable();
    $usuario = planeador();
    $pintada = piezaDeLaObra($obraId, 'PIP-CM1-1');
    $sinPintar = piezaDeLaObra($obraId, 'PIP-CM1-1');

    inspeccionDeAvance($pintada, FaseTransformacion::Segunda, Subetapa::Soldado, '2026-08-17');
    inspeccionDeAvance($sinPintar, FaseTransformacion::Segunda, Subetapa::Soldado, '2026-08-17');
    inspeccionDeAvance($pintada, FaseTransformacion::Tercera, null, '2026-08-19');

    planCerrado($this, $usuario, $obraId, '2026-S34', [$pintada, $sinPintar], fase: '3');

    $total = avanceQueVe($this, $usuario, ['obra' => $obraId, 'fase' => '3', 'semana' => '2026-S34'])['vista']['total'];

    expect($total)->toMatchArray(['programadas' => 2, 'fabricadas' => 1, 'liberadas' => 1, 'pendientes' => 1, 'empezadas' => 1]);
});

test('la portada compara las obras sin sumarlas y junta la cola de reparacion', function () {
    $conPlan = obraProgramable();
    $quieta = obraProgramable();
    $usuario = planeador();
    $rechazada = piezaDeLaObra($conPlan, 'PIP-CM1-1');

    planCerrado($this, $usuario, $conPlan, '2026-S34', [$rechazada]);
    inspeccionDeAvance($rechazada, FaseTransformacion::Segunda, Subetapa::Soldado, '2026-08-19', EstatusInspeccion::Rechazado);

    $comparativa = avanceQueVe($this, $usuario, ['semana' => '2026-S34'])['comparativa'];
    $porObra = collect($comparativa['resumenes'])->keyBy('obra_id');

    expect($porObra[$conPlan]['viva'])->toBeTrue()
        ->and($porObra[$conPlan]['fases']['2'])->toMatchArray(['programadas' => 1, 'fabricadas' => 1, 'enReparacion' => 1])
        ->and($porObra[$quieta]['viva'])->toBeFalse()
        ->and(collect($comparativa['reparaciones'])->pluck('qr')->all())->toBe([$rechazada->qr]);
});
