<?php

use App\Models\Rh\Candidatura;
use App\Models\Rh\Persona;
use App\Models\Rh\Requisicion;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['rh.candidaturas.ver', 'rh.candidaturas.crear'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $this->user->givePermissionTo(['rh.candidaturas.ver', 'rh.candidaturas.crear']);
});

describe('admin rh candidaturas', function () {
    test('candidatura can be created', function () {
        $requisicion = Requisicion::factory()->create();
        $persona = Persona::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.requisiciones.candidaturas.store', $requisicion), [
                'persona_id' => $persona->id,
                'notas' => 'Buen candidato',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rh_candidaturas', [
            'requisicion_id' => $requisicion->id,
            'persona_id' => $persona->id,
            'notas' => 'Buen candidato',
        ]);
    });

    test('candidatura can be deleted', function () {
        $candidatura = Candidatura::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.rh.requisiciones.candidaturas.destroy', [
                'requisicion' => $candidatura->requisicion_id,
                'candidatura' => $candidatura->id,
            ]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('rh_candidaturas', ['id' => $candidatura->id]);
    });

    test('candidatos page can be rendered', function () {
        $requisicion = Requisicion::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.rh.requisiciones.candidatos', $requisicion));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/rh/requisiciones/candidatos')
            ->has('requisicion')
        );
    });
});
