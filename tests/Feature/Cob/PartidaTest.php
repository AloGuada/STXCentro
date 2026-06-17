<?php

use App\Models\Cob\Partida;
use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->obra = Obra::factory()->create();
});

describe('admin cob partidas', function () {
    test('partida can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.cob.obras.partidas.store', $this->obra), [
                'obra_id' => $this->obra->id,
                'tipo' => 'suministro',
                'descripcion' => 'Estructura metalica',
                'monto' => 150000.00,
                'moneda' => 'MXN',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cob_partidas', [
            'obra_id' => $this->obra->id,
            'tipo' => 'suministro',
            'descripcion' => 'Estructura metalica',
        ]);
    });

    test('partida can be updated', function () {
        $partida = Partida::factory()->create(['obra_id' => $this->obra->id]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.cob.obras.partidas.update', [$this->obra, $partida]), [
                'obra_id' => $this->obra->id,
                'tipo' => 'montaje',
                'descripcion' => 'Partida actualizada',
                'monto' => 200000.00,
                'moneda' => 'USD',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cob_partidas', [
            'id' => $partida->id,
            'descripcion' => 'Partida actualizada',
            'tipo' => 'montaje',
        ]);
    });

    test('partida can be deleted', function () {
        $partida = Partida::factory()->create(['obra_id' => $this->obra->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.cob.obras.partidas.destroy', [$this->obra, $partida]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('cob_partidas', ['id' => $partida->id]);
    });
});
