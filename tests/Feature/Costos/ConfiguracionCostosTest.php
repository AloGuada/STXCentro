<?php

use App\Models\Costos\ConfiguracionCostos;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RubroAfectado;
use App\Models\Costos\SolicitudPago;
use App\Models\Departamento;
use App\Models\User;
use App\Services\Costos\ApartadoPresupuestal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

test('actual() devuelve una fila única con los valores por defecto', function () {
    $c = ConfiguracionCostos::actual();

    expect($c->dias_apartado)->toBe(5)
        ->and($c->dias_cancelar_requisicion)->toBe(10)
        ->and($c->dias_cancelar_solicitud)->toBe(10)
        ->and($c->corte_activo)->toBeTrue()
        ->and($c->corte_dia)->toBe(Carbon::WEDNESDAY)
        ->and($c->corte_hora)->toBe('13:00');

    ConfiguracionCostos::actual();
    expect(ConfiguracionCostos::count())->toBe(1);
});

test('el endpoint actualiza la configuración', function () {
    $this->actingAs(User::factory()->create())
        ->put('/admin/costos/configuracion', [
            'dias_apartado' => 7,
            'dias_cancelar_requisicion' => 15,
            'dias_cancelar_solicitud' => 20,
            'corte_activo' => false,
            'corte_dia' => 4,
            'corte_hora' => '09:30',
            'tolerancia_recepcion' => 0.05,
        ])
        ->assertRedirect();

    $c = ConfiguracionCostos::actual();
    expect($c->dias_apartado)->toBe(7)
        ->and($c->dias_cancelar_requisicion)->toBe(15)
        ->and($c->dias_cancelar_solicitud)->toBe(20)
        ->and($c->corte_activo)->toBeFalse()
        ->and($c->corte_dia)->toBe(4)
        ->and($c->corte_hora)->toBe('09:30')
        ->and($c->tolerancia_recepcion)->toBe(0.05);
});

test('la tolerancia de recepción nace en un centavo y no admite más de dos decimales', function () {
    expect(ConfiguracionCostos::actual()->tolerancia_recepcion)->toBe(0.01);

    $this->actingAs(User::factory()->create())
        ->put('/admin/costos/configuracion', [
            'dias_apartado' => 5,
            'dias_cancelar_requisicion' => 10,
            'dias_cancelar_solicitud' => 10,
            'corte_activo' => true,
            'corte_dia' => 3,
            'corte_hora' => '13:00',
            'tolerancia_recepcion' => 0.005,
        ])
        ->assertSessionHasErrors('tolerancia_recepcion');
});

test('con corte activo el viernes de la semana se bloquea tras el corte', function () {
    $config = ConfiguracionCostos::actual(); // miércoles 13:00

    Carbon::setTestNow(Carbon::parse('2026-07-08 12:00')); // miércoles, antes del corte
    expect($config->minViernes()->toDateString())->toBe('2026-07-10')
        ->and($config->fechaPagoValida(Carbon::parse('2026-07-10')))->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2026-07-08 13:30')); // miércoles, después del corte
    expect($config->minViernes()->toDateString())->toBe('2026-07-17')
        ->and($config->fechaPagoValida(Carbon::parse('2026-07-10')))->toBeFalse();

    Carbon::setTestNow();
});

test('sin corte siempre puede elegirse el viernes de la semana en curso', function () {
    $config = ConfiguracionCostos::actual();
    $config->update(['corte_activo' => false]);

    Carbon::setTestNow(Carbon::parse('2026-07-09 18:00')); // jueves por la tarde
    expect($config->minViernes()->toDateString())->toBe('2026-07-10')
        ->and($config->fechaPagoValida(Carbon::parse('2026-07-10')))->toBeTrue();

    Carbon::setTestNow();
});

test('fechaPagoValida rechaza cualquier día que no sea viernes', function () {
    $config = ConfiguracionCostos::actual();
    $config->update(['corte_activo' => false]);

    Carbon::setTestNow(Carbon::parse('2026-07-06 08:00')); // lunes
    expect($config->fechaPagoValida(Carbon::parse('2026-07-09')))->toBeFalse(); // jueves

    Carbon::setTestNow();
});

test('el apartado de presupuesto usa los días configurados', function () {
    ConfiguracionCostos::actual()->update(['dias_apartado' => 9]);

    $req = Requisicion::factory()->create();
    $rubro = ObraRubro::factory()->create();

    app(ApartadoPresupuestal::class)->apartarDocumento(
        $req,
        [['obra_rubro_id' => $rubro->id, 'monto' => 100, 'descripcion' => 'x']],
        User::factory()->create()->id,
    );

    $ra = RubroAfectado::where('entrada_type', Requisicion::class)->where('entrada_id', $req->id)->first();
    expect($ra->apartado_hasta->toDateString())->toBe(now()->addDays(9)->toDateString());
});

test('cancela solicitudes de pago en pendiente_firma vencidas según la config', function () {
    ConfiguracionCostos::actual()->update(['dias_cancelar_solicitud' => 10]);
    $depto = Departamento::factory()->create();

    $vieja = SolicitudPago::factory()->create(['departamento_id' => $depto->id, 'estatus' => 'pendiente_firma']);
    DB::table('costos_solicitudes_pago')->where('id', $vieja->id)->update(['updated_at' => now()->subDays(11)]);

    $reciente = SolicitudPago::factory()->create(['departamento_id' => $depto->id, 'estatus' => 'pendiente_firma']);

    $this->artisan('costos:cancelar-solicitudes-vencidas')->assertSuccessful();

    expect($vieja->fresh()->estatus->value)->toBe('cancelada')
        ->and($reciente->fresh()->estatus->value)->toBe('pendiente_firma');
});
