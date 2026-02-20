<?php

use App\Models\Infra\Bomba;
use App\Models\Infra\Turno;
use App\Models\Infra\TurnoDia;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('turno CRUD', function () {
    test('index page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.turnos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/infra/turnos/index')
            ->has('turnos')
            ->has('filters')
        );
    });

    test('index page can search by name', function () {
        Turno::factory()->create(['nombre' => 'R1 Matutino']);
        Turno::factory()->create(['nombre' => 'R2 Vespertino']);

        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.turnos.index', ['search' => 'Matutino']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('turnos.data', fn ($data) => count($data) === 1 && $data[0]['nombre'] === 'R1 Matutino')
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.turnos.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/infra/turnos/create')
        );
    });

    test('turno can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.infra.turnos.store'), [
                'nombre' => 'R1 Matutino',
                'hora_inicio' => '08:00',
                'hora_fin' => '12:00',
                'orden' => 1,
                'dias_semana' => [1, 2, 3, 4, 5],
            ]);

        $response->assertRedirect(route('admin.infra.turnos.index'));

        $this->assertDatabaseHas('infra_turnos', [
            'nombre' => 'R1 Matutino',
            'hora_inicio' => '08:00',
            'hora_fin' => '12:00',
            'orden' => 1,
        ]);

        $turno = Turno::where('nombre', 'R1 Matutino')->first();
        expect($turno->diasSemana)->toHaveCount(5);
    });

    test('store validates required fields', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.infra.turnos.store'), []);

        $response->assertSessionHasErrors(['nombre', 'hora_inicio', 'hora_fin', 'dias_semana']);
    });

    test('edit page can be rendered', function () {
        $turno = Turno::factory()->create();
        TurnoDia::factory()->create(['infra_turno_id' => $turno->id, 'dia_semana' => 1]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.infra.turnos.edit', $turno));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/infra/turnos/edit')
            ->has('turno')
            ->has('turno.dias_semana')
        );
    });

    test('turno can be updated', function () {
        $turno = Turno::factory()->create(['nombre' => 'Original']);
        TurnoDia::factory()->create(['infra_turno_id' => $turno->id, 'dia_semana' => 1]);

        $response = $this->actingAs($this->user)
            ->put(route('admin.infra.turnos.update', $turno), [
                'nombre' => 'Actualizado',
                'hora_inicio' => '13:00',
                'hora_fin' => '17:00',
                'orden' => 2,
                'dias_semana' => [6, 7],
            ]);

        $response->assertRedirect(route('admin.infra.turnos.index'));

        $turno->refresh();
        expect($turno->nombre)->toBe('Actualizado')
            ->and($turno->diasSemana)->toHaveCount(2);
    });

    test('turno can be destroyed when no records associated', function () {
        $turno = Turno::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.infra.turnos.destroy', $turno));

        $response->assertRedirect(route('admin.infra.turnos.index'));
        $this->assertDatabaseMissing('infra_turnos', ['id' => $turno->id]);
    });

    test('turno cannot be destroyed when records are associated', function () {
        $turno = Turno::factory()->create();
        Bomba::factory()->create(['infra_turno_id' => $turno->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.infra.turnos.destroy', $turno));

        $response->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('infra_turnos', ['id' => $turno->id]);
    });
});

describe('turno paraFecha', function () {
    test('returns turnos for correct day of week', function () {
        // Create turno active on Monday (1)
        $turno = Turno::factory()->create(['nombre' => 'Lunes only', 'activo' => true]);
        TurnoDia::factory()->create(['infra_turno_id' => $turno->id, 'dia_semana' => 1]); // Monday

        // 2026-02-16 is a Monday
        $result = Turno::paraFecha('2026-02-16');
        expect($result)->toHaveCount(1)
            ->and($result->first()->nombre)->toBe('Lunes only');

        // 2026-02-17 is a Tuesday
        $result = Turno::paraFecha('2026-02-17');
        expect($result)->toHaveCount(0);
    });

    test('does not return inactive turnos', function () {
        $turno = Turno::factory()->create(['activo' => false]);
        TurnoDia::factory()->create(['infra_turno_id' => $turno->id, 'dia_semana' => 1]);

        $result = Turno::paraFecha('2026-02-16'); // Monday
        expect($result)->toHaveCount(0);
    });

    test('returns turnos ordered by orden', function () {
        $t1 = Turno::factory()->create(['nombre' => 'Second', 'orden' => 2, 'activo' => true]);
        $t2 = Turno::factory()->create(['nombre' => 'First', 'orden' => 1, 'activo' => true]);
        TurnoDia::factory()->create(['infra_turno_id' => $t1->id, 'dia_semana' => 1]);
        TurnoDia::factory()->create(['infra_turno_id' => $t2->id, 'dia_semana' => 1]);

        $result = Turno::paraFecha('2026-02-16'); // Monday
        expect($result->first()->nombre)->toBe('First')
            ->and($result->last()->nombre)->toBe('Second');
    });
});

describe('turno auth', function () {
    test('unauthenticated users cannot access turnos', function () {
        $this->get(route('admin.infra.turnos.index'))
            ->assertRedirect('/login');
    });
});
