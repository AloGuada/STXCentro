<?php

use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\RubroAfectado;
use App\Models\Costos\SolicitudPago;
use App\Services\Costos\ApartadoPresupuestal;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->obraRubro = ObraRubro::factory()->create([
        'presupuestado' => 100000,
        'acumulado' => 0,
    ]);
    $this->service = app(ApartadoPresupuestal::class);
});

test('apartar incrementa acumulado y crea RubroAfectado en estatus Apartado', function () {
    $entrada = SolicitudPago::factory()->create();

    $this->service->apartarDocumento($entrada, [[
        'obra_rubro_id' => $this->obraRubro->id,
        'monto' => 5000,
        'descripcion' => 'Test',
    ]]);

    $this->obraRubro->refresh();
    expect((float) $this->obraRubro->acumulado)->toBe(5000.00);

    $ra = RubroAfectado::query()
        ->where('entrada_type', SolicitudPago::class)
        ->where('entrada_id', $entrada->id)
        ->first();

    expect($ra)->not->toBeNull();
    expect($ra->estatus)->toBe(RubroAfectadoEstatus::Apartado);
    expect((float) $ra->monto)->toBe(5000.00);
    expect($ra->apartado_hasta)->not->toBeNull();
    expect($ra->apartado_hasta->isAfter(now()->addDays(4)))->toBeTrue();
});

test('apartar permite sobregiro y marca el flag', function () {
    $entrada = SolicitudPago::factory()->create();

    $this->service->apartarDocumento($entrada, [[
        'obra_rubro_id' => $this->obraRubro->id,
        'monto' => 150000,
    ]]);

    $ra = RubroAfectado::query()
        ->where('entrada_type', SolicitudPago::class)
        ->where('entrada_id', $entrada->id)
        ->first();

    expect($ra->sobre_giro)->toBeTrue();
    expect($this->obraRubro->fresh()->disponible)->toBeLessThan(0);
});

test('convertirAPermanente muta Apartado a Aplicado sin tocar acumulado', function () {
    $entrada = SolicitudPago::factory()->create();
    $this->service->apartarDocumento($entrada, [[
        'obra_rubro_id' => $this->obraRubro->id,
        'monto' => 5000,
    ]]);

    $acumuladoAntes = (float) $this->obraRubro->fresh()->acumulado;

    $this->service->convertirAPermanente($entrada);

    $ra = RubroAfectado::query()
        ->where('entrada_type', SolicitudPago::class)
        ->where('entrada_id', $entrada->id)
        ->first();

    expect($ra->estatus)->toBe(RubroAfectadoEstatus::Aplicado);
    expect($ra->apartado_hasta)->toBeNull();
    expect((float) $this->obraRubro->fresh()->acumulado)->toBe($acumuladoAntes);
});

test('cancelarApartadosDe decrementa acumulado y marca Cancelado', function () {
    $entrada = SolicitudPago::factory()->create();
    $this->service->apartarDocumento($entrada, [[
        'obra_rubro_id' => $this->obraRubro->id,
        'monto' => 5000,
    ]]);

    expect((float) $this->obraRubro->fresh()->acumulado)->toBe(5000.00);

    $this->service->cancelarApartadosDe($entrada, 'test');

    expect((float) $this->obraRubro->fresh()->acumulado)->toBe(0.00);

    $ra = RubroAfectado::query()
        ->where('entrada_type', SolicitudPago::class)
        ->where('entrada_id', $entrada->id)
        ->first();
    expect($ra->estatus)->toBe(RubroAfectadoEstatus::Cancelado);
});

test('liberarVencidos libera apartados con apartado_hasta < hoy', function () {
    $entrada = SolicitudPago::factory()->create();

    Carbon::setTestNow('2026-05-01');
    $this->service->apartarDocumento($entrada, [[
        'obra_rubro_id' => $this->obraRubro->id,
        'monto' => 5000,
    ]]);

    expect((float) $this->obraRubro->fresh()->acumulado)->toBe(5000.00);

    Carbon::setTestNow('2026-05-07');

    $count = $this->service->liberarVencidos();

    expect($count)->toBe(1);
    expect((float) $this->obraRubro->fresh()->acumulado)->toBe(0.00);

    $ra = RubroAfectado::query()
        ->where('entrada_type', SolicitudPago::class)
        ->where('entrada_id', $entrada->id)
        ->first();
    expect($ra->estatus)->toBe(RubroAfectadoEstatus::Vencido);
    expect($ra->vencido_at)->not->toBeNull();

    Carbon::setTestNow();
});

test('liberarVencidos no toca apartados vigentes', function () {
    $entrada = SolicitudPago::factory()->create();

    Carbon::setTestNow('2026-05-01');
    $this->service->apartarDocumento($entrada, [[
        'obra_rubro_id' => $this->obraRubro->id,
        'monto' => 5000,
    ]]);

    Carbon::setTestNow('2026-05-03');
    $count = $this->service->liberarVencidos();

    expect($count)->toBe(0);
    expect((float) $this->obraRubro->fresh()->acumulado)->toBe(5000.00);

    Carbon::setTestNow();
});

test('reApartar crea apartado nuevo si los anteriores vencieron', function () {
    $entrada = SolicitudPago::factory()->create();

    Carbon::setTestNow('2026-05-01');
    $this->service->apartarDocumento($entrada, [[
        'obra_rubro_id' => $this->obraRubro->id,
        'monto' => 5000,
    ]]);

    Carbon::setTestNow('2026-05-07');
    $this->service->liberarVencidos();

    expect((float) $this->obraRubro->fresh()->acumulado)->toBe(0.00);

    $this->service->reApartarDocumento($entrada, [[
        'obra_rubro_id' => $this->obraRubro->id,
        'monto' => 5000,
    ]]);

    expect((float) $this->obraRubro->fresh()->acumulado)->toBe(5000.00);
    expect(RubroAfectado::where('entrada_id', $entrada->id)->where('estatus', 'apartado')->count())->toBe(1);

    Carbon::setTestNow();
});

test('reApartar es no-op si el documento todavía tiene apartado vigente', function () {
    $entrada = SolicitudPago::factory()->create();
    $this->service->apartarDocumento($entrada, [[
        'obra_rubro_id' => $this->obraRubro->id,
        'monto' => 5000,
    ]]);

    $this->service->reApartarDocumento($entrada, [[
        'obra_rubro_id' => $this->obraRubro->id,
        'monto' => 5000,
    ]]);

    expect(RubroAfectado::where('entrada_id', $entrada->id)->where('estatus', 'apartado')->count())->toBe(1);
    expect((float) $this->obraRubro->fresh()->acumulado)->toBe(5000.00);
});

test('comando costos:liberar-apartados-vencidos invoca el servicio', function () {
    $entrada = SolicitudPago::factory()->create();

    Carbon::setTestNow('2026-05-01');
    $this->service->apartarDocumento($entrada, [[
        'obra_rubro_id' => $this->obraRubro->id,
        'monto' => 5000,
    ]]);

    Carbon::setTestNow('2026-05-08');
    $this->artisan('costos:liberar-apartados-vencidos')
        ->expectsOutputToContain('Liberados 1 apartado(s) vencido(s).')
        ->assertSuccessful();

    Carbon::setTestNow();
});
