<?php

use App\Models\Costos\Requisicion;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('activity_log.causer_id no es entero para soportar causers con UUID', function () {
    expect(Schema::getColumnType('activity_log', 'causer_id'))
        ->not->toBeIn(['integer', 'bigint']);
});

test('crear un modelo con LogsActivity registra la actividad con el causer UUID', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $requisicion = Requisicion::factory()->create();

    $activity = $requisicion->activities()->latest('id')->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($user->id);
});
