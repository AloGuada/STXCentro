<?php

use App\Models\Cal\Soldador;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('can list soldadores', function () {
    Soldador::factory()->count(3)->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/soldadores');

    $response->assertOk()
        ->assertJsonCount(3);
});

test('can create soldador', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/soldadores', [
            'nombre' => 'Juan Soldador',
            'certificacion' => 'AWS-D1.1',
            'activo' => true,
        ]);

    $response->assertCreated()
        ->assertJsonFragment(['nombre' => 'Juan Soldador']);

    $this->assertDatabaseHas('cal_soldadores', ['nombre' => 'Juan Soldador']);
});

test('can update soldador', function () {
    $soldador = Soldador::factory()->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/soldadores/{$soldador->id}", [
            'nombre' => 'Nombre actualizado',
            'certificacion' => 'CWI',
        ]);

    $response->assertOk()
        ->assertJsonFragment(['nombre' => 'Nombre actualizado']);
});

test('can delete soldador', function () {
    $soldador = Soldador::factory()->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/soldadores/{$soldador->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('cal_soldadores', ['id' => $soldador->id]);
});
