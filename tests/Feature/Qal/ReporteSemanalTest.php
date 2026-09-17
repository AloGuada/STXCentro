<?php

use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\MetodoPnd;
use App\Enums\Qal\Subetapa;
use App\Models\Concepto;
use App\Models\Obra as ObraDelPortal;
use App\Models\Prod\Pieza;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Obra;
use App\Models\Qal\ObraPndPlan;
use App\Models\Qal\PndJunta;
use App\Models\Qal\PndReporte;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

/**
 * El reporte semanal (F-STX-CA-31).
 *
 * Aquí se prueban la hoja de PND, las de inspección visual y el corte de
 * año/semana; las de montaje van con las incidencias en obra. Las cuentas que
 * importan: en PND **la unidad es el spot** y el avance va sobre los spots
 * aceptados; en la inspección visual cada pieza cuenta una vez, en la semana en
 * que se liberó, y el porcentaje es liberadas con rechazo previo ÷ liberadas.
 */
function usuarioDelReporte(): User
{
    Permission::firstOrCreate(['name' => 'qal.reporte-semanal.ver', 'guard_name' => 'web']);

    $usuario = User::factory()->create();
    $usuario->givePermissionTo('qal.reporte-semanal.ver');

    return $usuario;
}

test('la pantalla abre con su permiso', function () {
    $this->actingAs(usuarioDelReporte())
        ->get(route('admin.qal.reporte-semanal'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/calidad/reporte-semanal/index'));
});

test('la pantalla queda cerrada sin el permiso', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.qal.reporte-semanal'))
        ->assertForbidden();
});

test('el permiso del tablero no abre el reporte', function () {
    Permission::firstOrCreate(['name' => 'qal.dashboard.ver', 'guard_name' => 'web']);

    $usuario = User::factory()->create();
    $usuario->givePermissionTo('qal.dashboard.ver');

    $this->actingAs($usuario)->get(route('admin.qal.reporte-semanal'))->assertForbidden();
});

test('la hoja de PND cuenta spots por metodo y mide el avance contra lo comprometido', function () {
    $obra = Obra::factory()->conNumero('T4 CANCUN')->create(['pz_total' => 1200]);

    ObraPndPlan::factory()->create([
        'qal_obra_id' => $obra->id,
        'metodo' => MetodoPnd::Ut,
        'comprometidas' => 40,
    ]);

    $ut = PndReporte::factory()->delMetodo(MetodoPnd::Ut)->create(['qal_obra_id' => $obra->id]);
    PndJunta::factory()->count(8)->create(['qal_pnd_reporte_id' => $ut->id, 'marca' => 'TP01-1']);
    PndJunta::factory()->rechazada()->count(2)->create(['qal_pnd_reporte_id' => $ut->id, 'marca' => 'TP02-1']);

    $mt = PndReporte::factory()->delMetodo(MetodoPnd::Mt)->create(['qal_obra_id' => $obra->id]);
    PndJunta::factory()->count(5)->create(['qal_pnd_reporte_id' => $mt->id, 'marca' => 'TP01-1']);

    $this->actingAs(usuarioDelReporte())
        ->get(route('admin.qal.reporte-semanal'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $page->has('pnd', 1)
                ->where('pnd.0.obra', 'T4 CANCUN')
                // 15 spots en total: los informes no se cuentan, ni las juntas.
                ->where('pnd.0.spots', 15)
                ->where('pnd.0.rechazados', 2)
                ->where('pnd.0.aceptados', 13)
                ->where('pnd.0.metodos.UT.spots', 10)
                ->where('pnd.0.metodos.UT.rechazados', 2)
                ->where('pnd.0.metodos.MT.spots', 5)
                // Un método sin ensayos llega en cero y la hoja lo pinta con una
                // raya; no se omite aquí, porque quien decide es la pantalla.
                ->where('pnd.0.metodos.RT.spots', 0)
                ->where('pnd.0.comprometidos', 40)
                ->where('pnd.0.pz_total', 1200)
                // Dos marcas distintas, una de ellas con un rechazo encima.
                ->where('pnd.0.piezas_con_pnd', 2)
                ->where('pnd.0.piezas_sin_rechazo', 1);
        });
});

test('una obra sin plan no inventa denominador', function () {
    $obra = Obra::factory()->conNumero('SIN PLAN')->create();
    $reporte = PndReporte::factory()->create(['qal_obra_id' => $obra->id]);
    PndJunta::factory()->count(3)->create(['qal_pnd_reporte_id' => $reporte->id]);

    $this->actingAs(usuarioDelReporte())
        ->get(route('admin.qal.reporte-semanal'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pnd.0.spots', 3)
            ->where('pnd.0.comprometidos', null)
            ->where('pnd.0.pz_total', null));
});

test('el corte cae en la semana mas reciente cuando se pide una que no existe', function () {
    $obra = Obra::factory()->create();
    PndReporte::factory()->create(['qal_obra_id' => $obra->id, 'anio' => 2025, 'semana' => 14]);
    PndReporte::factory()->create(['qal_obra_id' => $obra->id, 'anio' => 2025, 'semana' => 31]);

    $this->actingAs(usuarioDelReporte())
        ->get(route('admin.qal.reporte-semanal', ['anio' => 2025, 'semana' => 99]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('anio', 2025)
            ->where('semana', 31)
            ->where('semanas', [31, 14]));
});

/**
 * Sin un solo informe capturado la hoja de PND llega vacía y la pantalla escribe
 * de dónde saldrá, en vez de reventar o de pintar una tabla de ceros.
 */
test('sin informes la hoja de PND llega vacia', function () {
    $this->actingAs(usuarioDelReporte())
        ->get(route('admin.qal.reporte-semanal'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('pnd', [])->where('visual', [])->where('serie', []));
});

/** Una pieza física con QR en el catálogo vigente de la obra. */
function piezaDelReporte(ObraDelPortal $obra): Pieza
{
    return Pieza::factory()->create(['concepto_id' => Concepto::factory()->create(['obra_id' => $obra->id])->id]);
}

function inspeccionDelReporte(Pieza $pieza, FaseTransformacion $fase, string $fecha, EstatusInspeccion $estatus, float $kg = 100): void
{
    $dia = Carbon::parse($fecha);

    Inspeccion::factory()
        ->dePieza($pieza, $fase, $fase === FaseTransformacion::Segunda ? Subetapa::Soldado : null)
        ->create(['fecha' => $dia, 'anio' => $dia->isoWeekYear(), 'semana' => $dia->isoWeek(), 'estatus' => $estatus, 'kg' => $kg]);
}

test('la inspeccion visual cuenta cada pieza en la semana en que se libero, con su rechazo previo', function () {
    $obra = ObraDelPortal::factory()->create(['no' => 'T4 CANCUN']);

    // Semana 34 de 2026: del 17 al 23 de agosto.
    inspeccionDelReporte(piezaDelReporte($obra), FaseTransformacion::Segunda, '2026-08-19', EstatusInspeccion::Liberado, 500);
    $conRetrabajo = piezaDelReporte($obra);
    inspeccionDelReporte($conRetrabajo, FaseTransformacion::Segunda, '2026-08-12', EstatusInspeccion::Rechazado);
    inspeccionDelReporte($conRetrabajo, FaseTransformacion::Segunda, '2026-08-19', EstatusInspeccion::Liberado, 300);
    // Sigue rechazada: no se liberó, así que no entra en ninguna semana.
    inspeccionDelReporte(piezaDelReporte($obra), FaseTransformacion::Segunda, '2026-08-19', EstatusInspeccion::Rechazado);
    inspeccionDelReporte(piezaDelReporte($obra), FaseTransformacion::Tercera, '2026-08-20', EstatusInspeccion::Liberado);
    // Liberada la semana anterior: cuenta en la 33, no en la 34.
    inspeccionDelReporte(piezaDelReporte($obra), FaseTransformacion::Segunda, '2026-08-13', EstatusInspeccion::Liberado);

    $this->actingAs(usuarioDelReporte())
        ->get(route('admin.qal.reporte-semanal', ['anio' => 2026, 'semana' => 34]))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            expect($props['semana'])->toBe(34)
                ->and($props['semanas'])->toContain(34, 33)
                ->and($props['visual'])->toHaveCount(1)
                ->and($props['visual'][0])->toMatchArray([
                    'obra' => 'T4 CANCUN',
                    'liberadas2t' => 2,
                    'conRechazo2t' => 1,
                    'liberadasPintura' => 1,
                    'conRechazoPintura' => 0,
                ])
                ->and($props['kg_liberados'])->toEqual(800)
                // La misma cuenta semana a semana: la 33 liberó una sin rechazo
                // y la 34 dos, una con retrabajo. Pintura sin liberadas es nulo.
                ->and($props['serie'])->toEqual([
                    ['semana' => 33, 't2' => 0, 't3' => null],
                    ['semana' => 34, 't2' => 50, 't3' => 0],
                ]);
        });
});
