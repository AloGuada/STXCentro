<?php

use App\Models\Cob\Comparativo;
use App\Models\Proyecto;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->proyecto = Proyecto::factory()->create();
});

describe('admin cob comparativos', function () {
    test('comparativo can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.comparativos.store', $this->proyecto), [
                'descripcion' => 'Diferencia en volumen de acero',
                'monto_impacto' => 75000.00,
                'fecha_identificacion' => '2026-01-15',
                'estado' => 'analisis',
            ]);

        $response->assertRedirect(route('admin.cob.proyectos.show', $this->proyecto));
        $this->assertDatabaseHas('cob_comparativos', [
            'proyecto_id' => $this->proyecto->id,
            'descripcion' => 'Diferencia en volumen de acero',
            'estado' => 'analisis',
        ]);
    });

    test('comparativo can be updated', function () {
        $comparativo = Comparativo::factory()->create(['proyecto_id' => $this->proyecto->id]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.cob.proyectos.comparativos.update', [$this->proyecto, $comparativo]), [
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
        $comparativo = Comparativo::factory()->create(['proyecto_id' => $this->proyecto->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.cob.proyectos.comparativos.destroy', [$this->proyecto, $comparativo]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('cob_comparativos', ['id' => $comparativo->id]);
    });
});
