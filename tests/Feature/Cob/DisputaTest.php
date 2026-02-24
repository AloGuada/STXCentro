<?php

use App\Models\Cob\Disputa;
use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->obra = Obra::factory()->create();
});

describe('admin cob disputas', function () {
    test('disputa can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.cob.obras.disputas.store', $this->obra), [
                'obra_id' => $this->obra->id,
                'descripcion' => 'Disputa por alcance no definido',
                'fecha_inicio' => '2026-01-10',
                'fecha_resolucion' => null,
                'estado' => 'en_proceso',
                'resultado' => null,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cob_disputas', [
            'obra_id' => $this->obra->id,
            'descripcion' => 'Disputa por alcance no definido',
            'estado' => 'en_proceso',
        ]);
    });

    test('disputa can be updated', function () {
        $disputa = Disputa::factory()->create(['obra_id' => $this->obra->id]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.cob.obras.disputas.update', [$this->obra, $disputa]), [
                'obra_id' => $this->obra->id,
                'descripcion' => 'Disputa resuelta',
                'fecha_inicio' => '2026-01-10',
                'fecha_resolucion' => '2026-02-15',
                'estado' => 'resuelto',
                'resultado' => 'A favor del contratista',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cob_disputas', [
            'id' => $disputa->id,
            'descripcion' => 'Disputa resuelta',
            'estado' => 'resuelto',
        ]);
    });

    test('disputa can be deleted', function () {
        $disputa = Disputa::factory()->create(['obra_id' => $this->obra->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.cob.obras.disputas.destroy', [$this->obra, $disputa]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('cob_disputas', ['id' => $disputa->id]);
    });
});
