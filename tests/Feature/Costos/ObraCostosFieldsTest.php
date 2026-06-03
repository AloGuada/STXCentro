<?php

use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('obra costos fields', function () {
    test('obra can be created with new costos fields', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.obras.store'), [
                'no' => 'OBR-100',
                'descripcion' => 'Test Obra',
                'fecha_inicio' => '2026-03-01',
                'fecha_fin' => '2026-12-31',
                'presupuesto_total' => 1500000.50,
                'estatus' => 'abierta',
            ]);

        $response->assertRedirect(route('admin.obras.index'));
        $this->assertDatabaseHas('obras', [
            'no' => 'OBR-100',
            'estatus' => 'abierta',
        ]);
    });

    test('obra can be updated with new costos fields', function () {
        $obra = Obra::factory()->create(['estatus' => 'abierta']);

        $response = $this->actingAs($this->user)
            ->put(route('admin.obras.update', $obra), [
                'no' => $obra->no,
                'descripcion' => $obra->descripcion,
                'fecha_inicio' => '2026-01-01',
                'fecha_fin' => '2026-06-30',
                'presupuesto_total' => 2000000,
                'estatus' => 'cerrada',
            ]);

        $response->assertRedirect(route('admin.obras.index'));
        $this->assertDatabaseHas('obras', [
            'id' => $obra->id,
            'estatus' => 'cerrada',
        ]);
    });

    test('fecha_fin must be after fecha_inicio', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.obras.store'), [
                'no' => 'OBR-200',
                'descripcion' => 'Test',
                'fecha_inicio' => '2026-12-31',
                'fecha_fin' => '2026-01-01',
            ]);

        $response->assertSessionHasErrors(['fecha_fin']);
    });

    test('estatus must be valid value', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.obras.store'), [
                'no' => 'OBR-300',
                'descripcion' => 'Test',
                'estatus' => 'invalid_status',
            ]);

        $response->assertSessionHasErrors(['estatus']);
    });
});
