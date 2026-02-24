<?php

use App\Models\Cob\Comparativo;
use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->obra = Obra::factory()->create();
});

describe('admin cob comparativos', function () {
    test('comparativo can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.cob.obras.comparativos.store', $this->obra), [
                'obra_id' => $this->obra->id,
                'descripcion' => 'Diferencia en volumen de acero',
                'monto_impacto' => 75000.00,
                'fecha_identificacion' => '2026-01-15',
                'estado' => 'analisis',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cob_comparativos', [
            'obra_id' => $this->obra->id,
            'descripcion' => 'Diferencia en volumen de acero',
            'estado' => 'analisis',
        ]);
    });

    test('comparativo can be updated', function () {
        $comparativo = Comparativo::factory()->create(['obra_id' => $this->obra->id]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.cob.obras.comparativos.update', [$this->obra, $comparativo]), [
                'obra_id' => $this->obra->id,
                'descripcion' => 'Comparativo actualizado',
                'monto_impacto' => 120000.00,
                'fecha_identificacion' => '2026-02-01',
                'estado' => 'aprobado',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cob_comparativos', [
            'id' => $comparativo->id,
            'descripcion' => 'Comparativo actualizado',
            'estado' => 'aprobado',
        ]);
    });

    test('comparativo can be deleted', function () {
        $comparativo = Comparativo::factory()->create(['obra_id' => $this->obra->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.cob.obras.comparativos.destroy', [$this->obra, $comparativo]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('cob_comparativos', ['id' => $comparativo->id]);
    });
});
