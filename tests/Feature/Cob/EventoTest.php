<?php

use App\Models\Cob\Evento;
use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->obra = Obra::factory()->create();
});

describe('admin cob eventos', function () {
    test('evento can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.cob.obras.eventos.store', $this->obra), [
                'obra_id' => $this->obra->id,
                'parent_id' => null,
                'nombre' => 'Hito de fabricacion',
                'monto' => 100000.00,
                'inicio' => '2026-01-01',
                'fin' => '2026-03-31',
                'marcado' => false,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cob_eventos', [
            'obra_id' => $this->obra->id,
            'nombre' => 'Hito de fabricacion',
        ]);
    });

    test('evento can be updated', function () {
        $evento = Evento::factory()->create(['obra_id' => $this->obra->id]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.cob.obras.eventos.update', [$this->obra, $evento]), [
                'obra_id' => $this->obra->id,
                'parent_id' => null,
                'nombre' => 'Evento actualizado',
                'monto' => 200000.00,
                'inicio' => '2026-02-01',
                'fin' => '2026-04-30',
                'marcado' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cob_eventos', [
            'id' => $evento->id,
            'nombre' => 'Evento actualizado',
        ]);
    });

    test('evento can be deleted', function () {
        $evento = Evento::factory()->create(['obra_id' => $this->obra->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.cob.obras.eventos.destroy', [$this->obra, $evento]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('cob_eventos', ['id' => $evento->id]);
    });
});
