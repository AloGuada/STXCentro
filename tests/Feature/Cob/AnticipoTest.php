<?php

use App\Models\Cob\Anticipo;
use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->obra = Obra::factory()->create();
});

describe('admin cob anticipos', function () {
    test('anticipo can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.cob.obras.anticipos.store', $this->obra), [
                'obra_id' => $this->obra->id,
                'folio' => 'ANT-0001',
                'fecha_emision' => '2026-01-10',
                'monto' => 250000.00,
                'moneda' => 'MXN',
                'estado' => 'pendiente',
                'comentarios' => 'Anticipo inicial',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cob_anticipos', [
            'obra_id' => $this->obra->id,
            'folio' => 'ANT-0001',
            'estado' => 'pendiente',
        ]);
    });

    test('anticipo can be updated', function () {
        $anticipo = Anticipo::factory()->create(['obra_id' => $this->obra->id]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.cob.obras.anticipos.update', [$this->obra, $anticipo]), [
                'obra_id' => $this->obra->id,
                'folio' => 'ANT-UPDATED',
                'fecha_emision' => '2026-02-01',
                'monto' => 300000.00,
                'moneda' => 'USD',
                'estado' => 'pendiente',
                'comentarios' => 'Actualizado',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cob_anticipos', [
            'id' => $anticipo->id,
            'folio' => 'ANT-UPDATED',
        ]);
    });

    test('anticipo can be deleted', function () {
        $anticipo = Anticipo::factory()->create(['obra_id' => $this->obra->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.cob.obras.anticipos.destroy', [$this->obra, $anticipo]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('cob_anticipos', ['id' => $anticipo->id]);
    });

    test('anticipo can be marked as paid', function () {
        $anticipo = Anticipo::factory()->create([
            'obra_id' => $this->obra->id,
            'estado' => 'pendiente',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.cob.obras.anticipos.marcar-pagado', [$this->obra, $anticipo]));

        $response->assertRedirect();
        $anticipo->refresh();
        expect($anticipo->estado)->toBe('aplicado');
        expect($anticipo->fecha_pagado)->not->toBeNull();
    });
});
