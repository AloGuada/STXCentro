<?php

use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\RubroAfectado;
use App\Models\Costos\SolicitudPago;
use App\Models\User;
use App\Services\Costos\ApartadoPresupuestal;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('ejerce el presupuesto en MXN convirtiendo un cargo Aplicado desde USD', function () {
    $rubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0, 'apartado' => 0]);
    $sp = SolicitudPago::factory()->create();

    app(ApartadoPresupuestal::class)->aplicarCargo(
        entrada: $sp,
        obraRubroId: $rubro->id,
        monto: 100,
        estatus: RubroAfectadoEstatus::Aplicado,
        moneda: 'usd',
    );

    $ra = RubroAfectado::first();
    expect($ra->moneda)->toBe('usd');
    expect($ra->monto_origen)->toBe('100.00');
    expect((float) $ra->tipo_cambio)->toBe(18.5);
    expect($ra->monto)->toBe('1850.00');                 // 100 USD × 18.5
    expect((float) $rubro->fresh()->acumulado)->toBe(1850.0);
});

it('aparta el presupuesto en MXN convirtiendo desde EUR', function () {
    $rubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0, 'apartado' => 0]);
    $sp = SolicitudPago::factory()->create();

    app(ApartadoPresupuestal::class)->apartarDocumento($sp, [
        ['obra_rubro_id' => $rubro->id, 'monto' => 50, 'moneda' => 'eur'],
    ]);

    $ra = RubroAfectado::first();
    expect($ra->estatus)->toBe(RubroAfectadoEstatus::Apartado);
    expect($ra->monto)->toBe('1000.00');                 // 50 EUR × 20.0
    expect((float) $rubro->fresh()->apartado)->toBe(1000.0);
    expect((float) $rubro->fresh()->acumulado)->toBe(0.0); // apartar no toca el ejercido
});

it('usa el tipo de cambio guardado del documento, no el del servicio', function () {
    $rubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0, 'apartado' => 0]);
    $sp = SolicitudPago::factory()->create();

    // El documento guardó 17.0; el servicio falso daría 18.5. Debe ganar el doc.
    app(ApartadoPresupuestal::class)->apartarDocumento($sp, [
        ['obra_rubro_id' => $rubro->id, 'monto' => 100, 'moneda' => 'usd', 'tipo_cambio' => 17.0],
    ]);

    $ra = RubroAfectado::first();
    expect((float) $ra->tipo_cambio)->toBe(17.0);
    expect((float) $ra->monto)->toBe(1700.0);              // 100 × 17.0 (no 18.5)
    expect((float) $rubro->fresh()->apartado)->toBe(1700.0);
});

it('no convierte cuando la moneda es mxn', function () {
    $rubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0, 'apartado' => 0]);
    $sp = SolicitudPago::factory()->create();

    app(ApartadoPresupuestal::class)->aplicarCargo(
        entrada: $sp,
        obraRubroId: $rubro->id,
        monto: 500,
        estatus: RubroAfectadoEstatus::Aplicado,
    );

    $ra = RubroAfectado::first();
    expect($ra->moneda)->toBe('mxn');
    expect((float) $ra->tipo_cambio)->toBe(1.0);
    expect($ra->monto)->toBe('500.00');
    expect((float) $rubro->fresh()->acumulado)->toBe(500.0);
});
