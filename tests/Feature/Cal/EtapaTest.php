<?php

use App\Models\Cal\Etapa;
use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('can list etapas filtered by obra_id', function () {
    $obra = Obra::factory()->create();
    Etapa::factory()->count(3)->create(['obra_id' => $obra->id]);
    Etapa::factory()->create(); // otra obra

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/etapas?obra_id={$obra->id}");

    $response->assertOk()
        ->assertJsonCount(3);
});

test('can create etapa', function () {
    $obra = Obra::factory()->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/etapas', [
            'descripcion' => 'Etapa de prueba',
            'obra_id' => $obra->id,
        ]);

    $response->assertCreated()
        ->assertJsonFragment(['descripcion' => 'Etapa de prueba']);

    $this->assertDatabaseHas('cal_etapas', ['descripcion' => 'Etapa de prueba']);
});

test('can update etapa', function () {
    $etapa = Etapa::factory()->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/etapas/{$etapa->id}", [
            'descripcion' => 'Etapa actualizada',
            'obra_id' => $etapa->obra_id,
        ]);

    $response->assertOk()
        ->assertJsonFragment(['descripcion' => 'Etapa actualizada']);
});

test('can delete etapa', function () {
    $etapa = Etapa::factory()->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/etapas/{$etapa->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('cal_etapas', ['id' => $etapa->id]);
});

test('etapa validation requires descripcion and obra_id', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/etapas', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['descripcion', 'obra_id']);
});
