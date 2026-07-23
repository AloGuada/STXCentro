<?php

use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Pago;
use App\Models\Costos\RubroAfectado;
use App\Models\Costos\SolicitudPago;
use App\Models\User;
use App\Services\Costos\ApartadoPresupuestal;
use App\Services\Costos\ReconciliacionCambioPago;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function ejercerUsd(SolicitudPago $sp, ObraRubro $rubro, float $montoUsd): void
{
    app(ApartadoPresupuestal::class)->aplicarCargo(
        entrada: $sp,
        obraRubroId: $rubro->id,
        monto: $montoUsd,
        estatus: RubroAfectadoEstatus::Aplicado,
        moneda: 'usd',
    );
}

it('reconcilia un pago en USD ajustando el acumulado al MXN real', function () {
    $rubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0, 'apartado' => 0]);
    $sp = SolicitudPago::factory()->create(['orden_compra_id' => null]);
    ejercerUsd($sp, $rubro, 100);                          // 100 USD × 18.5 = 1850 MXN (referencia)

    $pago = Pago::factory()->create([
        'pagable_type' => SolicitudPago::class,
        'pagable_id' => $sp->id,
        'monto_pago' => 100,
        'moneda' => 'usd',
        'tipo_cambio' => 18.5,             // referencia heredada del documento
    ]);

    app(ReconciliacionCambioPago::class)->reconciliar($pago, 1900); // banco pagó 1900 MXN reales

    expect((float) $rubro->fresh()->acumulado)->toBe(1900.0);
    $ra = RubroAfectado::where('estatus', RubroAfectadoEstatus::Aplicado->value)->first();
    expect((float) $ra->monto)->toBe(1900.0);
    expect((float) $ra->tipo_cambio)->toBe(19.0);          // 1900 / 100
    expect((float) $pago->fresh()->monto_mxn)->toBe(1900.0);
    expect((float) $pago->fresh()->tipo_cambio)->toBe(19.0);
});

it('prorratea el delta entre los rubros afectados por su peso', function () {
    $rubroA = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0, 'apartado' => 0]);
    $rubroB = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0, 'apartado' => 0]);
    $sp = SolicitudPago::factory()->create(['orden_compra_id' => null]);
    ejercerUsd($sp, $rubroA, 100);                          // 1850 MXN
    ejercerUsd($sp, $rubroB, 200);                          // 3700 MXN  → referencia 5550

    $pago = Pago::factory()->create([
        'pagable_type' => SolicitudPago::class,
        'pagable_id' => $sp->id,
        'monto_pago' => 300,
        'moneda' => 'usd',
        'tipo_cambio' => 18.5,             // referencia heredada del documento
    ]);

    app(ReconciliacionCambioPago::class)->reconciliar($pago, 5661); // delta = 111

    // rubroA: 111 × 1850/5550 = 37.00 → 1887 ; rubroB: 111 − 37 = 74.00 → 3774
    expect((float) $rubroA->fresh()->acumulado)->toBe(1887.0);
    expect((float) $rubroB->fresh()->acumulado)->toBe(3774.0);
    // La suma reconciliada iguala el MXN real exacto.
    expect((float) $rubroA->fresh()->acumulado + (float) $rubroB->fresh()->acumulado)->toBe(5661.0);
});

it('con pagos parciales cada comprobante ajusta solo su porción, no la entidad completa', function () {
    $rubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0, 'apartado' => 0]);
    $sp = SolicitudPago::factory()->create(['orden_compra_id' => null]);
    ejercerUsd($sp, $rubro, 200);          // 200 USD × 18.5 = 3700 MXN (referencia total)

    $pago1 = Pago::factory()->create([
        'pagable_type' => SolicitudPago::class, 'pagable_id' => $sp->id,
        'monto_pago' => 100, 'moneda' => 'usd', 'tipo_cambio' => 18.5,
    ]);
    $pago2 = Pago::factory()->create([
        'pagable_type' => SolicitudPago::class, 'pagable_id' => $sp->id,
        'monto_pago' => 100, 'moneda' => 'usd', 'tipo_cambio' => 18.5,
    ]);

    // Parcialidad 1: banco pagó 1900 (peor TC). Ajusta solo su porción:
    // 1900 − (100 × 18.5 = 1850) = +50. NO reconcilia los 3700 completos.
    app(ReconciliacionCambioPago::class)->reconciliar($pago1, 1900);
    expect((float) $rubro->fresh()->acumulado)->toBe(3750.0);

    // Parcialidad 2: al TC de referencia (1850) → delta 0, sin cambio extra.
    app(ReconciliacionCambioPago::class)->reconciliar($pago2, 1850);
    expect((float) $rubro->fresh()->acumulado)->toBe(3750.0);
});

it('un pago en mxn no toca el presupuesto, solo sella el monto_mxn', function () {
    $rubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0, 'apartado' => 0]);
    $sp = SolicitudPago::factory()->create(['orden_compra_id' => null]);
    app(ApartadoPresupuestal::class)->aplicarCargo(
        entrada: $sp,
        obraRubroId: $rubro->id,
        monto: 500,
        estatus: RubroAfectadoEstatus::Aplicado,
    );

    $pago = Pago::factory()->create([
        'pagable_type' => SolicitudPago::class,
        'pagable_id' => $sp->id,
        'monto_pago' => 500,
        'moneda' => 'mxn',
    ]);

    app(ReconciliacionCambioPago::class)->reconciliar($pago, 500);

    expect((float) $rubro->fresh()->acumulado)->toBe(500.0);
    expect((float) $pago->fresh()->monto_mxn)->toBe(500.0);
});
