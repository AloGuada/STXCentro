<?php

use App\Models\Cob\Adenda;
use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->obra = Obra::factory()->create();
});

describe('admin cob adendas', function () {
    test('adenda can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.cob.obras.adendas.store', $this->obra), [
                'obra_id' => $this->obra->id,
                'tipo' => 'aumento',
                'descripcion' => 'Aumento por cambio de alcance',
                'monto_modificacion' => 150000.00,
                'fecha' => '2026-01-20',
                'estado' => 'borrador',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cob_adendas', [
            'obra_id' => $this->obra->id,
            'tipo' => 'aumento',
            'descripcion' => 'Aumento por cambio de alcance',
        ]);
    });

    test('adenda can be updated', function () {
        $adenda = Adenda::factory()->create(['obra_id' => $this->obra->id]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.cob.obras.adendas.update', [$this->obra, $adenda]), [
                'obra_id' => $this->obra->id,
                'tipo' => 'reduccion',
                'descripcion' => 'Adenda actualizada',
                'monto_modificacion' => 80000.00,
                'fecha' => '2026-02-01',
                'estado' => 'en_revision',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cob_adendas', [
            'id' => $adenda->id,
            'tipo' => 'reduccion',
            'descripcion' => 'Adenda actualizada',
        ]);
    });

    test('adenda can be deleted', function () {
        $adenda = Adenda::factory()->create(['obra_id' => $this->obra->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.cob.obras.adendas.destroy', [$this->obra, $adenda]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('cob_adendas', ['id' => $adenda->id]);
    });
});
