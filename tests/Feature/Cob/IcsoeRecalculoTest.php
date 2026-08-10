<?php

use App\Enums\Cob\IcsoeEstatus;
use App\Models\Cob\Comparativo;
use App\Models\Cob\IcsoeSeguimiento;
use App\Models\Cob\Partida;
use App\Models\Obra;
use App\Models\Proyecto;
use App\Services\Cob\IcsoeService;
use Illuminate\Support\Facades\DB;

/**
 * Proyecto a precio unitario con seguimiento ICSOE ya calculado.
 *
 * @return array{0: Proyecto, 1: Obra, 2: IcsoeSeguimiento}
 */
function proyectoConIcsoe(float $montoComparativo = 1000000, string $tipoContrato = 'precio_unitario'): array
{
    $proyecto = Proyecto::factory()->create();
    $obra = Obra::factory()->create([
        'proyecto_id' => $proyecto->id,
        'tipo' => 'base',
        'tipo_contrato' => $tipoContrato,
    ]);

    Comparativo::factory()->create([
        'proyecto_id' => $proyecto->id,
        'obra_id' => $obra->id,
        'monto_impacto' => $montoComparativo,
    ]);

    $seguimiento = app(IcsoeService::class)->crear($proyecto, [
        'metodo' => 'porcentaje',
        'fecha_inicio' => '2026-01-01',
        'fecha_fin' => '2026-03-31',
        'porcentaje_mo' => 30,
        'prima_riesgo' => 7.58875,
    ]);

    return [$proyecto, $obra, $seguimiento];
}

it('marca pendiente al cambiar el monto de un comparativo', function () {
    [$proyecto, $obra, $seguimiento] = proyectoConIcsoe(1000000);

    expect((float) $seguimiento->monto_base)->toBe(1000000.0);

    $proyecto->comparativos()->first()->update(['monto_impacto' => 1500000]);

    $seguimiento->refresh();

    expect($seguimiento->estatus)->toBe(IcsoeEstatus::PendienteVerificacion);
    expect((float) $seguimiento->monto_base)->toBe(1500000.0);
    expect((float) $seguimiento->monto_base_anterior)->toBe(1000000.0);
    expect((float) $seguimiento->mo_estimada_total)->toBe(450000.0);
    expect((float) $seguimiento->mo_estimada_total_anterior)->toBe(300000.0);
    expect($seguimiento->motivo_cambio)->toBe('Cambió el comparativo de ingeniería');
});

it('marca pendiente al crear un comparativo nuevo', function () {
    [$proyecto, $obra, $seguimiento] = proyectoConIcsoe(1000000);

    Comparativo::factory()->create([
        'proyecto_id' => $proyecto->id,
        'obra_id' => $obra->id,
        'monto_impacto' => 2000000,
    ]);

    $seguimiento->refresh();

    expect($seguimiento->estatus)->toBe(IcsoeEstatus::PendienteVerificacion);
    expect((float) $seguimiento->monto_base)->toBe(2000000.0);
});

it('marca pendiente al eliminar el comparativo', function () {
    [$proyecto, $obra, $seguimiento] = proyectoConIcsoe(1000000);

    $proyecto->comparativos()->first()->delete();

    $seguimiento->refresh();

    expect($seguimiento->estatus)->toBe(IcsoeEstatus::PendienteVerificacion);
    // Sin comparativos, la obra unitaria vuelve a valer sus partidas (ninguna).
    expect((float) $seguimiento->monto_base)->toBe(0.0);
});

it('marca pendiente al mover una partida de la obra', function () {
    [$proyecto, $obra, $seguimiento] = proyectoConIcsoe(500000, 'precio_alzado');

    Partida::factory()->create(['obra_id' => $obra->id, 'monto' => 250000]);

    $seguimiento->refresh();

    expect($seguimiento->estatus)->toBe(IcsoeEstatus::PendienteVerificacion);
    expect((float) $seguimiento->monto_base)->toBe(250000.0);
});

it('no truena si el proyecto no tiene seguimiento', function () {
    $proyecto = Proyecto::factory()->create();
    $obra = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base']);

    Comparativo::factory()->create(['proyecto_id' => $proyecto->id, 'obra_id' => $obra->id]);
    Partida::factory()->create(['obra_id' => $obra->id, 'monto' => 1000]);

    expect(IcsoeSeguimiento::count())->toBe(0);
});

it('en método superficie cambia la base pero no la meta', function () {
    $proyecto = Proyecto::factory()->create();
    $obra = Obra::factory()->create([
        'proyecto_id' => $proyecto->id,
        'tipo' => 'base',
        'tipo_contrato' => 'precio_alzado',
    ]);
    Partida::factory()->create(['obra_id' => $obra->id, 'monto' => 100000]);

    $seguimiento = app(IcsoeService::class)->crear($proyecto, [
        'metodo' => 'superficie',
        'fecha_inicio' => '2026-01-01',
        'fecha_fin' => '2026-03-31',
        'superficie_m2' => 1164,
        'costo_m2' => 1154,
        'prima_riesgo' => 7.58875,
    ]);

    Partida::factory()->create(['obra_id' => $obra->id, 'monto' => 900000]);

    $seguimiento->refresh();

    expect($seguimiento->estatus)->toBe(IcsoeEstatus::PendienteVerificacion);
    expect((float) $seguimiento->monto_base)->toBe(1000000.0);
    expect((float) $seguimiento->mo_estimada_total)->toBe(1343256.0);
    expect((float) $seguimiento->mo_estimada_total_anterior)->toBe(1343256.0);
});

it('colapsa un lote de partidas en un solo recálculo', function () {
    [$proyecto, $obra, $seguimiento] = proyectoConIcsoe(500000, 'precio_alzado');

    $recalculos = 0;
    DB::listen(function ($query) use (&$recalculos) {
        if (str_contains($query->sql, 'update "cob_icsoe_seguimientos"')) {
            $recalculos++;
        }
    });

    DB::transaction(function () use ($obra) {
        Partida::factory()->create(['obra_id' => $obra->id, 'monto' => 100000]);
        Partida::factory()->create(['obra_id' => $obra->id, 'monto' => 200000]);
        Partida::factory()->create(['obra_id' => $obra->id, 'monto' => 300000]);
    });

    $seguimiento->refresh();

    expect((float) $seguimiento->monto_base)->toBe(600000.0);
    // Un solo recálculo (que escribe dos veces: el seguimiento y sus totales).
    expect($recalculos)->toBeLessThanOrEqual(2);
});

it('el propio recálculo no vuelve a dispararse a sí mismo', function () {
    [$proyecto, $obra, $seguimiento] = proyectoConIcsoe(1000000);

    app(IcsoeService::class)->recalcular($seguimiento->fresh(), 'Manual');

    expect($seguimiento->fresh()->estatus)->toBe(IcsoeEstatus::Vigente);
});

it('ignora los cambios de obra que no alteran el valor a ejecutar', function () {
    [$proyecto, $obra, $seguimiento] = proyectoConIcsoe(1000000);

    $obra->update(['descripcion' => 'Otro nombre']);

    expect($seguimiento->fresh()->estatus)->toBe(IcsoeEstatus::Vigente);
});

it('recalcula al cambiar el tipo de contrato de la obra', function () {
    [$proyecto, $obra, $seguimiento] = proyectoConIcsoe(1000000);

    // La partida no mueve nada mientras la obra sea unitaria: manda el comparativo.
    Partida::factory()->create(['obra_id' => $obra->id, 'monto' => 42000]);
    expect($seguimiento->fresh()->estatus)->toBe(IcsoeEstatus::Vigente);

    $obra->update(['tipo_contrato' => 'precio_alzado']);

    $seguimiento->refresh();

    expect($seguimiento->estatus)->toBe(IcsoeEstatus::PendienteVerificacion);
    expect((float) $seguimiento->monto_base)->toBe(42000.0);
});

it('cierra el seguimiento cuando se cierra el proyecto y lo reabre al reabrirlo', function () {
    [$proyecto, $obra, $seguimiento] = proyectoConIcsoe(1000000);

    $proyecto->update(['estatus' => 'cerrada']);
    expect($seguimiento->fresh()->estatus)->toBe(IcsoeEstatus::Cerrado);

    $proyecto->update(['estatus' => 'abierta']);
    expect($seguimiento->fresh()->estatus)->toBe(IcsoeEstatus::Vigente);
});

it('no recalcula un seguimiento cerrado', function () {
    [$proyecto, $obra, $seguimiento] = proyectoConIcsoe(1000000);

    $proyecto->update(['estatus' => 'cerrada']);
    $proyecto->comparativos()->first()->update(['monto_impacto' => 9999999]);

    $seguimiento->refresh();

    expect($seguimiento->estatus)->toBe(IcsoeEstatus::Cerrado);
    expect((float) $seguimiento->monto_base)->toBe(1000000.0);
});
