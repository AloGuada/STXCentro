<?php

use App\Models\Cal\Flecha;
use App\Models\Cal\PiezaPlano;
use App\Models\Cal\Reporte;
use App\Models\Cal\Soldador;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('can list reportes filtered by plano_id', function () {
    $plano = PiezaPlano::factory()->create();
    Reporte::factory()->count(2)->create(['plano_id' => $plano->id]);
    Reporte::factory()->create(); // otro plano

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/reportes?plano_id={$plano->id}");

    $response->assertOk()
        ->assertJsonCount(2);
});

test('can create reporte', function () {
    $plano = PiezaPlano::factory()->create();
    $soldador = Soldador::factory()->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/reportes', [
            'plano_id' => $plano->id,
            'consecutivo' => '001',
            'inspector_id' => $this->user->id,
            'soldador_id' => $soldador->id,
            'es_plantilla' => false,
        ]);

    $response->assertCreated()
        ->assertJsonFragment(['consecutivo' => '001']);
});

test('can update reporte', function () {
    $reporte = Reporte::factory()->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/reportes/{$reporte->id}", [
            'plano_id' => $reporte->plano_id,
            'comentario' => 'Observación actualizada',
        ]);

    $response->assertOk()
        ->assertJsonFragment(['comentario' => 'Observación actualizada']);
});

test('can delete reporte', function () {
    $reporte = Reporte::factory()->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/reportes/{$reporte->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('cal_reportes', ['id' => $reporte->id]);
});

test('can copy reporte as plantilla', function () {
    $reporte = Reporte::factory()->plantilla()->create();
    Flecha::factory()->count(3)->create(['reporte_id' => $reporte->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/reportes/{$reporte->id}/copiar", [
            'consecutivo' => '002',
        ]);

    $response->assertCreated();

    $nuevoReporte = Reporte::find($response->json('id'));
    expect($nuevoReporte)->not->toBeNull();
    expect($nuevoReporte->flechas)->toHaveCount(3);
    expect($nuevoReporte->es_plantilla)->toBeFalse();
});

test('can delete all flechas from reporte', function () {
    $reporte = Reporte::factory()->create();
    Flecha::factory()->count(5)->create(['reporte_id' => $reporte->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/reportes/{$reporte->id}/flechas");

    $response->assertNoContent();
    $this->assertDatabaseCount('cal_flechas', 0);
});
