<?php

use App\Models\Costos\ConfiguracionCostos;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RubroAfectado;
use App\Models\Costos\SolicitudPago;
use App\Models\Departamento;
use App\Models\User;
use App\Services\Costos\ApartadoPresupuestal;
use Illuminate\Support\Facades\DB;

test('actual() devuelve una fila única con los valores por defecto', function () {
    $c = ConfiguracionCostos::actual();

    expect($c->dias_apartado)->toBe(5)
        ->and($c->dias_cancelar_requisicion)->toBe(10)
        ->and($c->dias_cancelar_solicitud)->toBe(10);

    ConfiguracionCostos::actual();
    expect(ConfiguracionCostos::count())->toBe(1);
});

test('el endpoint actualiza la configuración', function () {
    $this->actingAs(User::factory()->create())
        ->put('/admin/costos/configuracion', [
            'dias_apartado' => 7,
            'dias_cancelar_requisicion' => 15,
            'dias_cancelar_solicitud' => 20,
        ])
        ->assertRedirect();

    $c = ConfiguracionCostos::actual();
    expect($c->dias_apartado)->toBe(7)
        ->and($c->dias_cancelar_requisicion)->toBe(15)
        ->and($c->dias_cancelar_solicitud)->toBe(20);
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
