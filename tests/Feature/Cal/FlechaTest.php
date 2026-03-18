<?php

use App\Models\Cal\Flecha;
use App\Models\Cal\Reporte;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('can list flechas filtered by reporte_id', function () {
    $reporte = Reporte::factory()->create();
    Flecha::factory()->count(4)->create(['reporte_id' => $reporte->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/flechas?reporte_id={$reporte->id}");

    $response->assertOk()
        ->assertJsonCount(4);
});

test('can create flecha', function () {
    $reporte = Reporte::factory()->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/flechas', [
            'reporte_id' => $reporte->id,
            'inicio_x' => 0.1234,
            'inicio_y' => 0.5678,
            'fin_x' => 0.9012,
            'fin_y' => 0.3456,
            'esdoble' => true,
            'tipo' => 'filete',
            'show_number' => true,
            'pagina' => 2,
        ]);

    $response->assertCreated()
        ->assertJsonFragment(['tipo' => 'filete', 'pagina' => 2]);
});

test('can update flecha', function () {
    $flecha = Flecha::factory()->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/flechas/{$flecha->id}", [
            'reporte_id' => $flecha->reporte_id,
            'inicio_x' => 0.5,
            'inicio_y' => 0.5,
            'fin_x' => 0.8,
            'fin_y' => 0.8,
            'tipo' => 'penetracion',
        ]);

    $response->assertOk()
        ->assertJsonFragment(['tipo' => 'penetracion']);
});

test('can delete flecha', function () {
    $flecha = Flecha::factory()->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/flechas/{$flecha->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('cal_flechas', ['id' => $flecha->id]);
});
