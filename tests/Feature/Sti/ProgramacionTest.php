<?php

use App\Models\Sti\Equipo;
use App\Models\Sti\Plan;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('programacion de mantenimientos', function () {
    test('programacion page loads with equipos and planes', function () {
        $equipo = Equipo::factory()->create();
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.mantenimientos.programacion'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/sti/mantenimientos/programacion')
            ->has('equipos', 1)
            ->has('planes', 1)
            ->has('year')
        );
    });

    test('generar creates mantenimientos for a single equipo with fecha_inicial', function () {
        $plan = Plan::factory()->create(['periodicidad' => 30]);
        $equipo = Equipo::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.mantenimientos.generar'), [
                'plan_id' => $plan->id,
                'equipo_id' => $equipo->id,
                'fecha_inicial' => '2026-01-15',
                'year' => 2026,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sti_mantenimientos', [
            'plan_id' => $plan->id,
            'equipo_id' => $equipo->id,
        ]);
    });

    test('generar validates required fields', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.mantenimientos.generar'), []);

        $response->assertSessionHasErrors(['plan_id', 'equipo_id', 'fecha_inicial', 'year']);
    });

    test('generar validates equipo_id exists', function () {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.mantenimientos.generar'), [
                'plan_id' => $plan->id,
                'equipo_id' => 9999,
                'fecha_inicial' => '2026-01-15',
                'year' => 2026,
            ]);

        $response->assertSessionHasErrors(['equipo_id']);
    });

    test('generar validates fecha_inicial is a valid date', function () {
        $plan = Plan::factory()->create();
        $equipo = Equipo::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.mantenimientos.generar'), [
                'plan_id' => $plan->id,
                'equipo_id' => $equipo->id,
                'fecha_inicial' => 'not-a-date',
                'year' => 2026,
            ]);

        $response->assertSessionHasErrors(['fecha_inicial']);
    });
});
