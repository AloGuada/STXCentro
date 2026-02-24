<?php

use App\Models\Cob\Penalizacion;
use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->obra = Obra::factory()->create();
});

describe('admin cob penalizaciones', function () {
    test('penalizacion can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.cob.obras.penalizaciones.store', $this->obra), [
                'obra_id' => $this->obra->id,
                'descripcion' => 'Penalizacion por retraso en entrega',
                'monto' => 50000.00,
                'moneda' => 'MXN',
                'tipo' => 'retraso',
                'fecha' => '2026-01-25',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cob_penalizaciones', [
            'obra_id' => $this->obra->id,
            'descripcion' => 'Penalizacion por retraso en entrega',
            'tipo' => 'retraso',
        ]);
    });

    test('penalizacion can be updated', function () {
        $penalizacion = Penalizacion::factory()->create(['obra_id' => $this->obra->id]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.cob.obras.penalizaciones.update', [$this->obra, $penalizacion]), [
                'obra_id' => $this->obra->id,
                'descripcion' => 'Penalizacion actualizada',
                'monto' => 75000.00,
                'moneda' => 'USD',
                'tipo' => 'calidad',
                'fecha' => '2026-02-01',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cob_penalizaciones', [
            'id' => $penalizacion->id,
            'descripcion' => 'Penalizacion actualizada',
            'tipo' => 'calidad',
        ]);
    });

    test('penalizacion can be deleted', function () {
        $penalizacion = Penalizacion::factory()->create(['obra_id' => $this->obra->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.cob.obras.penalizaciones.destroy', [$this->obra, $penalizacion]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('cob_penalizaciones', ['id' => $penalizacion->id]);
    });
});
