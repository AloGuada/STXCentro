<?php

use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\RubroAfectado;
use App\Models\Costos\SolicitudPago;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * Simula un cargo histórico "en crudo": el RubroAfectado quedó con el número de
 * la divisa (moneda='mxn' por el default viejo) y el acumulado del rubro igual.
 */
function cargoCrudoUsd(float $montoCrudo = 100): array
{
    $rubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => $montoCrudo, 'apartado' => 0]);
    $oc = OrdenCompra::factory()->create(['moneda' => 'usd']);
    $ra = RubroAfectado::create([
        'entrada_type' => OrdenCompra::class,
        'entrada_id' => $oc->id,
        'obra_rubro_id' => $rubro->id,
        'monto' => $montoCrudo,
        'moneda' => 'mxn',
        'tipo_movimiento' => 'cargo',
        'estatus' => RubroAfectadoEstatus::Aplicado,
        'fecha_aplicacion' => now(),
    ]);

    return [$rubro, $ra];
}

it('recalcula un cargo Aplicado en crudo de una OC en USD con el TC de hoy', function () {
    [$rubro, $ra] = cargoCrudoUsd(100);

    $this->artisan('costos:recalcular-tipo-cambio', ['--moneda' => 'usd'])->assertSuccessful();

    $ra->refresh();
    expect($ra->moneda)->toBe('usd');
    expect((float) $ra->monto_origen)->toBe(100.0);
    expect((float) $ra->tipo_cambio)->toBe(18.5);
    expect((float) $ra->monto)->toBe(1850.0);              // 100 × 18.5
    expect((float) $rubro->fresh()->acumulado)->toBe(1850.0);
});

it('en dry-run no escribe nada', function () {
    [$rubro, $ra] = cargoCrudoUsd(100);

    $this->artisan('costos:recalcular-tipo-cambio', ['--moneda' => 'usd', '--dry-run' => true])->assertSuccessful();

    expect($ra->fresh()->moneda)->toBe('mxn');
    expect((float) $rubro->fresh()->acumulado)->toBe(100.0);
});

it('recalcula una solicitud objetivo con el TC guardado del documento', function () {
    // Afectación sellada a 18.5 (acumulado 1850); la SP ahora guarda TC 19.0.
    $rubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 1850, 'apartado' => 0]);
    $sp = SolicitudPago::factory()->create(['tipo_moneda' => 'usd', 'tipo_cambio' => 19.0, 'orden_compra_id' => null]);
    RubroAfectado::create([
        'entrada_type' => SolicitudPago::class,
        'entrada_id' => $sp->id,
        'obra_rubro_id' => $rubro->id,
        'monto' => 1850,
        'moneda' => 'usd',
        'monto_origen' => 100,
        'tipo_cambio' => 18.5,
        'tipo_movimiento' => 'cargo',
        'estatus' => RubroAfectadoEstatus::Aplicado,
        'fecha_aplicacion' => now(),
    ]);

    $this->artisan('costos:recalcular-tipo-cambio', ['--solicitud' => [$sp->id]])->assertSuccessful();

    $ra = RubroAfectado::first();
    expect((float) $ra->tipo_cambio)->toBe(19.0);
    expect((float) $ra->monto)->toBe(1900.0);              // 100 × 19.0
    expect((float) $rubro->fresh()->acumulado)->toBe(1900.0); // 1850 + 50
});

it('es idempotente: no re-convierte un cargo ya sellado en divisa', function () {
    $rubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 1850, 'apartado' => 0]);
    $oc = OrdenCompra::factory()->create(['moneda' => 'usd']);
    RubroAfectado::create([
        'entrada_type' => OrdenCompra::class,
        'entrada_id' => $oc->id,
        'obra_rubro_id' => $rubro->id,
        'monto' => 1850,
        'moneda' => 'usd',                                 // ya convertido
        'monto_origen' => 100,
        'tipo_cambio' => 18.5,
        'tipo_movimiento' => 'cargo',
        'estatus' => RubroAfectadoEstatus::Aplicado,
        'fecha_aplicacion' => now(),
    ]);

    $this->artisan('costos:recalcular-tipo-cambio', ['--moneda' => 'usd'])->assertSuccessful();

    expect((float) $rubro->fresh()->acumulado)->toBe(1850.0); // sin cambios
});
