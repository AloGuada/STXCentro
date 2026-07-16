<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\SolicitudPagoDetalle;
use App\Models\User;
use App\Services\Costos\ApartadoPresupuestal;

beforeEach(function () {
    $this->user = User::factory()->create();
});

/**
 * Crea una solicitud en pendiente_firma con apartado vivo y una cadena de
 * aprobación de niveles 1..4 (el 4 = Director).
 *
 * @return array{0: SolicitudPago, 1: ObraRubro}
 */
function solicitudConCadena(User $user): array
{
    $obraRubro = ObraRubro::factory()->create(['presupuestado' => 100000, 'acumulado' => 0]);
    $solicitud = SolicitudPago::factory()->create(['estatus' => 'pendiente_firma', 'monto_total' => 5000]);
    SolicitudPagoDetalle::factory()->create([
        'solicitud_id' => $solicitud->id,
        'obra_rubro_id' => $obraRubro->id,
        'subtotal' => 5000,
    ]);

    // Apartado vivo (como si se hubiera enviado a aprobación): ya suma al acumulado.
    app(ApartadoPresupuestal::class)->apartarDocumento(
        $solicitud,
        [['obra_rubro_id' => $obraRubro->id, 'monto' => 5000, 'descripcion' => 'test']],
        $user->id,
    );

    foreach ([1, 2, 3, 4] as $nivel) {
        $solicitud->aprobaciones()->create([
            'nivel' => $nivel,
            'aprobador_id' => User::factory()->create()->id,
            'estatus' => 'pendiente',
        ]);
    }

    return [$solicitud, $obraRubro];
}

test('costos:aprobar firma la solicitud saltando el director y consolida el acumulado', function () {
    [$solicitud, $obraRubro] = solicitudConCadena($this->user);

    $this->artisan('costos:aprobar', [
        'identificador' => $solicitud->folio,
        '--saltar-nivel' => [4],
        '--motivo' => 'test',
    ])->assertSuccessful();

    $solicitud->refresh();
    expect($solicitud->estatus->value)->toBe('aprobada');

    // El apartado pasó a aplicado (permanente, ya no vence).
    expect($solicitud->rubrosAfectados()->where('estatus', 'aplicado')->exists())->toBeTrue();
    expect($solicitud->rubrosAfectados()->where('estatus', 'apartado')->exists())->toBeFalse();

    // El nivel Director quedó cancelado (no firmó).
    expect($solicitud->aprobaciones()->where('nivel', 4)->where('estatus', 'cancelada')->exists())->toBeTrue();

    // El acumulado no se duplicó: sigue en 5000 (el apartado ya estaba contado).
    expect((float) $obraRubro->fresh()->acumulado)->toBe(5000.00);
});

test('costos:aprobar por id también funciona', function () {
    [$solicitud] = solicitudConCadena($this->user);

    $this->artisan('costos:aprobar', [
        'identificador' => (string) $solicitud->id,
        '--tipo' => 'solicitud',
        '--saltar-nivel' => [4],
    ])->assertSuccessful();

    expect($solicitud->fresh()->estatus->value)->toBe('aprobada');
});

test('costos:aprobar rechaza una solicitud en borrador', function () {
    $solicitud = SolicitudPago::factory()->create(['estatus' => 'borrador']);

    $this->artisan('costos:aprobar', ['identificador' => $solicitud->folio])
        ->assertFailed();

    expect($solicitud->fresh()->estatus->value)->toBe('borrador');
});

test('costos:aprobar es no-op si ya está aprobada', function () {
    $solicitud = SolicitudPago::factory()->create(['estatus' => 'aprobada']);

    $this->artisan('costos:aprobar', ['identificador' => $solicitud->folio])
        ->assertSuccessful();
});

test('costos:aprobar --dry-run no cambia nada', function () {
    [$solicitud] = solicitudConCadena($this->user);

    $this->artisan('costos:aprobar', [
        'identificador' => $solicitud->folio,
        '--saltar-nivel' => [4],
        '--dry-run' => true,
    ])->assertSuccessful();

    expect($solicitud->fresh()->estatus->value)->toBe('pendiente_firma');
});
