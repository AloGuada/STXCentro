<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\SolicitudPagoDetalle;
use App\Services\Costos\ReasignacionCentroCostos;
use Spatie\Activitylog\Models\Activity;

/**
 * Reproduce el daño histórico: reasigna y además sobrescribe el monto_total con
 * la suma del desglose, que es justo lo que hacía el servicio antes del arreglo.
 */
function reasignarComoAntesDelArreglo(SolicitudPago $solicitud, array $nuevosDetalles, string $motivo): void
{
    app(ReasignacionCentroCostos::class)->reasignar($solicitud, $nuevosDetalles, $motivo);

    $suma = round(array_sum(array_map(fn (array $d): float => (float) $d['monto'], $nuevosDetalles)), 2);
    $solicitud->update(['monto_total' => $suma]);
}

test('el comando restaura el monto original sobrescrito por la reasignación', function () {
    $origen = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

    $sp = SolicitudPago::factory()->create([
        'estatus' => 'aprobada',
        'monto_total' => 9926.15,
        'orden_compra_id' => null,
    ]);
    SolicitudPagoDetalle::factory()->create([
        'solicitud_id' => $sp->id,
        'obra_rubro_id' => $origen->id,
        'cantidad' => 1,
        'precio_unitario' => 9926.15,
        'subtotal' => 9926.15,
    ]);
    $sp->load('detalles');
    $sp->aplicarImpactoPresupuestal();

    reasignarComoAntesDelArreglo($sp, [
        ['obra_rubro_id' => $destino->id, 'monto' => 12381.65, 'concepto' => 'Comprobación real'],
    ], 'Se reasignó a la semana siguiente');

    expect((float) $sp->fresh()->monto_total)->toBe(12381.65);

    // Dry-run: reporta pero no escribe.
    $this->artisan('costos:reparar-monto-total-reasignado')
        ->expectsOutputToContain('DRY-RUN')
        ->assertSuccessful();

    expect((float) $sp->fresh()->monto_total)->toBe(12381.65);

    $this->artisan('costos:reparar-monto-total-reasignado', ['--force' => true])
        ->assertSuccessful();

    expect((float) $sp->fresh()->monto_total)->toBe(9926.15);

    // El desglose y el presupuesto no se tocan: siguen valiendo lo reasignado.
    expect((float) $sp->fresh()->detalles->sum('subtotal'))->toBe(12381.65);
    expect((float) $destino->fresh()->acumulado)->toBe(12381.65);
    expect((float) $origen->fresh()->acumulado)->toBe(0.00);
});

test('con varias reasignaciones recupera el monto anterior a la primera', function () {
    $a = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $b = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $c = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

    $sp = SolicitudPago::factory()->create([
        'estatus' => 'aprobada',
        'monto_total' => 2692.67,
        'orden_compra_id' => null,
    ]);
    SolicitudPagoDetalle::factory()->create([
        'solicitud_id' => $sp->id,
        'obra_rubro_id' => $a->id,
        'cantidad' => 1,
        'precio_unitario' => 2692.67,
        'subtotal' => 2692.67,
    ]);
    $sp->load('detalles');
    $sp->aplicarImpactoPresupuestal();

    reasignarComoAntesDelArreglo($sp, [
        ['obra_rubro_id' => $b->id, 'monto' => 5000, 'concepto' => 'Primera'],
    ], 'Primera reasignación del expediente');

    reasignarComoAntesDelArreglo($sp, [
        ['obra_rubro_id' => $c->id, 'monto' => 9800, 'concepto' => 'Segunda'],
    ], 'Segunda reasignación del expediente');

    expect((float) $sp->fresh()->monto_total)->toBe(9800.00);

    $this->artisan('costos:reparar-monto-total-reasignado', ['--force' => true])
        ->assertSuccessful();

    expect((float) $sp->fresh()->monto_total)->toBe(2692.67);
});

test('no toca las solicitudes reasignadas con el código ya corregido', function () {
    $origen = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

    $sp = SolicitudPago::factory()->create([
        'estatus' => 'aprobada',
        'monto_total' => 5000,
        'orden_compra_id' => null,
    ]);
    SolicitudPagoDetalle::factory()->create([
        'solicitud_id' => $sp->id,
        'obra_rubro_id' => $origen->id,
        'cantidad' => 1,
        'precio_unitario' => 5000,
        'subtotal' => 5000,
    ]);
    $sp->load('detalles');
    $sp->aplicarImpactoPresupuestal();

    // El servicio actual no toca el monto aunque el reparto sume distinto.
    app(ReasignacionCentroCostos::class)->reasignar($sp, [
        ['obra_rubro_id' => $destino->id, 'monto' => 3000, 'concepto' => 'Sólo esta parte'],
    ], 'Reasignación con el código ya corregido');

    $this->artisan('costos:reparar-monto-total-reasignado', ['--force' => true])
        ->expectsOutputToContain('No hay nada que restaurar')
        ->assertSuccessful();

    expect((float) $sp->fresh()->monto_total)->toBe(5000.00);
});

test('marca para revisión manual si algo movió el monto después de la reasignación', function () {
    $origen = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

    $sp = SolicitudPago::factory()->create([
        'estatus' => 'aprobada',
        'monto_total' => 4000,
        'orden_compra_id' => null,
    ]);
    SolicitudPagoDetalle::factory()->create([
        'solicitud_id' => $sp->id,
        'obra_rubro_id' => $origen->id,
        'cantidad' => 1,
        'precio_unitario' => 4000,
        'subtotal' => 4000,
    ]);
    $sp->load('detalles');
    $sp->aplicarImpactoPresupuestal();

    reasignarComoAntesDelArreglo($sp, [
        ['obra_rubro_id' => $destino->id, 'monto' => 6000, 'concepto' => 'Reasignado'],
    ], 'Reasignación del expediente');

    // Alguien más movió el monto por fuera y sin dejarlo en la bitácora.
    SolicitudPago::withoutEvents(fn () => $sp->update(['monto_total' => 7777]));

    $this->artisan('costos:reparar-monto-total-reasignado', ['--force' => true])
        ->expectsOutputToContain('REVISAR')
        ->assertSuccessful();

    expect((float) $sp->fresh()->monto_total)->toBe(7777.00);
});

test('la reparación queda registrada en la bitácora', function () {
    $origen = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);

    $sp = SolicitudPago::factory()->create([
        'estatus' => 'aprobada',
        'monto_total' => 1520.74,
        'orden_compra_id' => null,
    ]);
    SolicitudPagoDetalle::factory()->create([
        'solicitud_id' => $sp->id,
        'obra_rubro_id' => $origen->id,
        'cantidad' => 1,
        'precio_unitario' => 1520.74,
        'subtotal' => 1520.74,
    ]);
    $sp->load('detalles');
    $sp->aplicarImpactoPresupuestal();

    reasignarComoAntesDelArreglo($sp, [
        ['obra_rubro_id' => $destino->id, 'monto' => 2383.08],
    ], 'Reasignación del expediente');

    $this->artisan('costos:reparar-monto-total-reasignado', ['--force' => true])
        ->assertSuccessful();

    $registro = Activity::query()
        ->where('subject_type', SolicitudPago::class)
        ->where('subject_id', $sp->id)
        ->where('description', 'Monto total restaurado tras reasignación')
        ->first();

    expect($registro)->not->toBeNull();
    expect((float) data_get($registro->properties, 'monto_sobrescrito'))->toBe(2383.08);
    expect((float) data_get($registro->properties, 'monto_restaurado'))->toBe(1520.74);
});

test('--solicitud acota la reparación a un solo folio', function () {
    $rubro = ObraRubro::factory()->create(['presupuestado' => 500000, 'acumulado' => 0]);
    $destino = ObraRubro::factory()->create(['presupuestado' => 500000, 'acumulado' => 0]);

    $hacerSolicitud = function (float $monto, float $reasignado) use ($rubro, $destino): SolicitudPago {
        $sp = SolicitudPago::factory()->create([
            'estatus' => 'aprobada',
            'monto_total' => $monto,
            'orden_compra_id' => null,
        ]);
        SolicitudPagoDetalle::factory()->create([
            'solicitud_id' => $sp->id,
            'obra_rubro_id' => $rubro->id,
            'cantidad' => 1,
            'precio_unitario' => $monto,
            'subtotal' => $monto,
        ]);
        $sp->load('detalles');
        $sp->aplicarImpactoPresupuestal();

        reasignarComoAntesDelArreglo($sp, [
            ['obra_rubro_id' => $destino->id, 'monto' => $reasignado],
        ], 'Reasignación del expediente');

        return $sp;
    };

    $reparar = $hacerSolicitud(1000, 1800);
    $intacta = $hacerSolicitud(2000, 2600);

    $this->artisan('costos:reparar-monto-total-reasignado', [
        '--solicitud' => $reparar->folio,
        '--force' => true,
    ])->assertSuccessful();

    expect((float) $reparar->fresh()->monto_total)->toBe(1000.00);
    expect((float) $intacta->fresh()->monto_total)->toBe(2600.00);
});
