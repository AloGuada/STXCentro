<?php

use App\Models\Cob\Estimacion;
use App\Models\Cob\EstimacionEstadoHistorial;
use App\Models\Cob\EstimacionPago;
use App\Models\Cob\Retencion;
use App\Models\Media;

function estimacionConTodo(): Estimacion
{
    $estimacion = Estimacion::factory()->create(['folio' => 'EST-0001']);

    $pago = EstimacionPago::factory()->create(['estimacion_id' => $estimacion->id]);
    Media::factory()->create([
        'mediable_type' => EstimacionPago::class,
        'mediable_id' => $pago->id,
    ]);
    Retencion::factory()->create(['estimacion_id' => $estimacion->id]);
    EstimacionEstadoHistorial::factory()->create(['estimacion_id' => $estimacion->id]);

    return $estimacion;
}

it('en dry-run no borra nada', function () {
    $estimacion = estimacionConTodo();

    $this->artisan('cob:borrar-estimacion', ['identificador' => 'EST-0001'])
        ->assertSuccessful();

    $this->assertDatabaseHas('cob_estimaciones', ['id' => $estimacion->id]);
    $this->assertDatabaseHas('cob_estimaciones_pagos', ['estimacion_id' => $estimacion->id]);
});

it('con --force borra la estimación y todo lo relacionado', function () {
    $estimacion = estimacionConTodo();
    $pagoId = EstimacionPago::where('estimacion_id', $estimacion->id)->value('id');

    $this->artisan('cob:borrar-estimacion', ['identificador' => 'EST-0001', '--force' => true])
        ->assertSuccessful();

    $this->assertDatabaseMissing('cob_estimaciones', ['id' => $estimacion->id]);
    $this->assertDatabaseMissing('cob_estimaciones_pagos', ['estimacion_id' => $estimacion->id]);
    $this->assertDatabaseMissing('cob_retenciones', ['estimacion_id' => $estimacion->id]);
    $this->assertDatabaseMissing('cob_estimacion_estado_historial', ['estimacion_id' => $estimacion->id]);
    $this->assertDatabaseMissing('media', ['mediable_type' => EstimacionPago::class, 'mediable_id' => $pagoId]);
});

it('también resuelve la estimación por id', function () {
    $estimacion = Estimacion::factory()->create(['folio' => null]);

    $this->artisan('cob:borrar-estimacion', ['identificador' => (string) $estimacion->id, '--force' => true])
        ->assertSuccessful();

    $this->assertDatabaseMissing('cob_estimaciones', ['id' => $estimacion->id]);
});

it('falla si la estimación no existe', function () {
    $this->artisan('cob:borrar-estimacion', ['identificador' => 'NO-EXISTE'])
        ->assertFailed();
});
