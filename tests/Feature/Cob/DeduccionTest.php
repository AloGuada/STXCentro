<?php

use App\Models\Cob\Deduccion;
use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->obra = Obra::factory()->create();
});

describe('admin cob deducciones', function () {
    test('deduccion can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.cob.obras.deducciones.store', $this->obra), [
                'obra_id' => $this->obra->id,
                'descripcion' => 'Deduccion por retraso',
                'monto' => 25000.00,
                'moneda' => 'MXN',
                'fecha' => '2026-01-20',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cob_deducciones', [
            'obra_id' => $this->obra->id,
            'descripcion' => 'Deduccion por retraso',
        ]);
    });

    test('deduccion can be updated', function () {
        $deduccion = Deduccion::factory()->create(['obra_id' => $this->obra->id]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.cob.obras.deducciones.update', [$this->obra, $deduccion]), [
                'obra_id' => $this->obra->id,
                'descripcion' => 'Deduccion actualizada',
                'monto' => 35000.00,
                'moneda' => 'USD',
                'fecha' => '2026-02-01',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cob_deducciones', [
            'id' => $deduccion->id,
            'descripcion' => 'Deduccion actualizada',
        ]);
    });

    test('deduccion can be deleted', function () {
        $deduccion = Deduccion::factory()->create(['obra_id' => $this->obra->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.cob.obras.deducciones.destroy', [$this->obra, $deduccion]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('cob_deducciones', ['id' => $deduccion->id]);
    });
});
