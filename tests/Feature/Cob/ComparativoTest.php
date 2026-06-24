<?php

use App\Models\Cob\Comparativo;
use App\Models\Obra;
use App\Models\Proyecto;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->proyecto = Proyecto::factory()->create();
    $this->obra = Obra::factory()->create(['proyecto_id' => $this->proyecto->id, 'tipo' => 'base']);
});

describe('admin cob comparativos', function () {
    test('comparativo can be stored ligado a una obra', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.comparativos.store', $this->proyecto), [
                'obra_id' => $this->obra->id,
                'descripcion' => 'Diferencia en volumen de acero',
                'monto_impacto' => 75000.00,
                'fecha_identificacion' => '2026-01-15',
                'estado' => 'analisis',
            ]);

        $response->assertRedirect(route('admin.cob.proyectos.show', $this->proyecto));
        $this->assertDatabaseHas('cob_comparativos', [
            'proyecto_id' => $this->proyecto->id,
            'obra_id' => $this->obra->id,
            'descripcion' => 'Diferencia en volumen de acero',
        ]);
    });

    test('rechaza una obra que no pertenece al proyecto', function () {
        $ajena = Obra::factory()->create(['tipo' => 'base']);

        $this->actingAs($this->user)
            ->post(route('admin.cob.proyectos.comparativos.store', $this->proyecto), [
                'obra_id' => $ajena->id,
                'descripcion' => 'x',
                'monto_impacto' => 1000,
                'estado' => 'analisis',
            ])
            ->assertNotFound();
    });

    test('comparativo can be updated', function () {
        $comparativo = Comparativo::factory()->create([
            'proyecto_id' => $this->proyecto->id,
            'obra_id' => $this->obra->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.cob.proyectos.comparativos.update', [$this->proyecto, $comparativo]), [
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
        $comparativo = Comparativo::factory()->create([
            'proyecto_id' => $this->proyecto->id,
            'obra_id' => $this->obra->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.cob.proyectos.comparativos.destroy', [$this->proyecto, $comparativo]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('cob_comparativos', ['id' => $comparativo->id]);
    });
});
