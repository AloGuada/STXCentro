<?php

use App\Models\Cob\Estimacion;
use App\Models\Cob\Partida;
use App\Models\Cob\ReporteNota;
use App\Models\Obra;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->anio = 2026;
    $this->semana = 10;
});

/** Lunes 10:00 de la semana ISO dada del año de prueba. */
function diaDeSemana(int $anio, int $semana): Carbon
{
    return Carbon::now()->setISODate($anio, $semana)->startOfWeek()->addDay()->setTime(10, 0);
}

/** Crea una obra (no planta) con created_at forzado y partidas por los montos dados. */
function obraConPartidas(Carbon $creadaEn, array $montos): Obra
{
    $obra = Obra::factory()->create(['es_planta' => false]);
    DB::table('obras')->where('id', $obra->id)->update(['created_at' => $creadaEn]);
    foreach ($montos as $monto) {
        Partida::factory()->create(['obra_id' => $obra->id, 'monto' => $monto]);
    }

    return $obra;
}

it('una estimación global (sin obra) pagada en la semana suma al cobrado y al saldo', function () {
    $proyecto = Proyecto::factory()->create();
    Estimacion::factory()->global()->create([
        'proyecto_id' => $proyecto->id,
        'estado' => 'pagado',
        'fecha_ultimo_cambio_estado' => diaDeSemana($this->anio, $this->semana),
        'monto_estimado' => 3000,
        'monto_total' => 3480,
    ]);

    $this->actingAs($this->user)
        ->get(route('admin.cob.reportes.show', ['anio' => $this->anio, 'semana' => $this->semana]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reporte.total_cobrado_sin_iva', 3000)
            ->where('reporte.total_cobrado_con_iva', 3480)
            ->where('reporte.saldo_nuevo_sin_iva', -3000) // sin detonaciones: 0 + 0 - 3000
            ->has('reporte.cobros', 1)
            ->where('reporte.cobros.0.obra_no', "Global · {$proyecto->no}")
        );
});

it('calcula detonaciones (Σ partidas con IVA, sin IVA = ÷1.16) y cobros de la semana', function () {
    $dentro = diaDeSemana($this->anio, $this->semana);
    $obra = obraConPartidas($dentro, [1000, 500]); // con IVA 1500, sin IVA 1293.10

    Estimacion::factory()->create([
        'obra_id' => $obra->id,
        'estado' => 'pagado',
        'fecha_ultimo_cambio_estado' => $dentro,
        'monto_estimado' => 2000,
        'monto_total' => 2320,
    ]);

    $this->actingAs($this->user)
        ->get(route('admin.cob.reportes.show', ['anio' => $this->anio, 'semana' => $this->semana]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/cob/reportes/show')
            ->where('reporte.total_detonaciones_sin_iva', 1293.1) // 1500 / 1.16
            ->where('reporte.total_detonaciones_con_iva', 1500)
            ->where('reporte.total_cobrado_sin_iva', 2000)
            ->where('reporte.total_cobrado_con_iva', 2320)
            ->where('reporte.saldo_anterior_sin_iva', 0)
            // 0 + 1293.10 - 2000 = -706.90
            ->where('reporte.saldo_nuevo_sin_iva', -706.9)
            ->has('reporte.detonaciones', 1)
            ->has('reporte.cobros', 1)
        );
});

it('el saldo anterior acumula los movimientos de semanas previas', function () {
    // Obra detonada en la semana 8 (antes de la 10). Monto con IVA 1000 → sin IVA 862.07.
    obraConPartidas(diaDeSemana($this->anio, 8), [1000]);

    $this->actingAs($this->user)
        ->get(route('admin.cob.reportes.show', ['anio' => $this->anio, 'semana' => $this->semana]))
        ->assertInertia(fn ($page) => $page
            ->where('reporte.saldo_anterior_sin_iva', 862.07) // 1000 / 1.16
            ->where('reporte.total_detonaciones_sin_iva', 0)
            ->where('reporte.saldo_nuevo_sin_iva', 862.07)
        );
});

it('guarda y muestra las notas de la semana', function () {
    $this->actingAs($this->user)
        ->put(route('admin.cob.reportes.notas', ['anio' => $this->anio, 'semana' => $this->semana]), [
            'notas' => 'Ajuste manual por nota de crédito',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('cob_reporte_notas', [
        'anio' => $this->anio,
        'semana' => $this->semana,
        'notas' => 'Ajuste manual por nota de crédito',
    ]);

    $this->actingAs($this->user)
        ->get(route('admin.cob.reportes.show', ['anio' => $this->anio, 'semana' => $this->semana]))
        ->assertInertia(fn ($page) => $page->where('reporte.notas', 'Ajuste manual por nota de crédito'));
});

it('actualizar notas dos veces no duplica el registro', function () {
    $payload = fn (string $n) => $this->actingAs($this->user)
        ->put(route('admin.cob.reportes.notas', ['anio' => $this->anio, 'semana' => $this->semana]), ['notas' => $n]);

    $payload('uno')->assertRedirect();
    $payload('dos')->assertRedirect();

    expect(ReporteNota::where('anio', $this->anio)->where('semana', $this->semana)->count())->toBe(1)
        ->and(ReporteNota::where('anio', $this->anio)->where('semana', $this->semana)->value('notas'))->toBe('dos');
});

it('la tabla anual lista una fila por semana del año', function () {
    $this->actingAs($this->user)
        ->get(route('admin.cob.reportes.index', ['anio' => 2025]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/cob/reportes/index')
            ->where('anio', 2025)
            ->has('filas', 52) // 2025 tiene 52 semanas ISO
        );
});

it('marca la fila con notas en la tabla anual', function () {
    ReporteNota::create(['anio' => 2025, 'semana' => 5, 'notas' => 'x']);

    $this->actingAs($this->user)
        ->get(route('admin.cob.reportes.index', ['anio' => 2025]))
        ->assertInertia(fn ($page) => $page
            ->where('filas', fn ($filas) => collect($filas)->firstWhere('semana', 5)['tiene_notas'] === true)
        );
});

it('descarga el PDF de la semana', function () {
    $this->actingAs($this->user)
        ->get(route('admin.cob.reportes.pdf', ['anio' => $this->anio, 'semana' => $this->semana]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
