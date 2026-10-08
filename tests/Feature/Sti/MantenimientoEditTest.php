<?php

use App\Models\Sti\Mantenimiento;
use App\Models\Sti\Plan;
use App\Models\Sti\Tecnico;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('edit page', function () {
    test('edit page can be rendered', function () {
        $mantenimiento = Mantenimiento::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.mantenimientos.edit', $mantenimiento));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/sti/mantenimientos/edit')
            ->where('mantenimiento.id', $mantenimiento->id)
            ->has('equipos')
            ->has('tecnicos')
        );
    });

    test('edit page can be rendered for a plan mantenimiento without tecnico', function () {
        $plan = Plan::factory()->create(['fecha_inicial' => '2026-01-01', 'periodicidad' => 30]);
        $mantenimiento = Mantenimiento::factory()->create(['plan_id' => $plan->id, 'tecnico_id' => null]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.mantenimientos.edit', $mantenimiento));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('mantenimiento.tecnico_id', null)
            ->where('mantenimiento.plan.id', $plan->id)
        );
    });

    test('edit page can be rendered for a realizado mantenimiento without fecha_realizado', function () {
        $mantenimiento = Mantenimiento::factory()->create(['status' => 'realizado', 'fecha_realizado' => null]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.mantenimientos.edit', $mantenimiento));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('mantenimiento.status', 'realizado')
            ->where('mantenimiento.fecha_realizado', null)
        );
    });

    test('costos are sent as decimal strings', function () {
        $mantenimiento = Mantenimiento::factory()->create();
        $mantenimiento->costos()->create(['descripcion' => 'Refaccion', 'cantidad' => 150]);
        $mantenimiento->costos()->create(['descripcion' => 'Mano de obra', 'cantidad' => 200.5]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.mantenimientos.edit', $mantenimiento));

        $response->assertInertia(fn ($page) => $page
            ->has('mantenimiento.costos', 2)
            ->where('mantenimiento.costos.0.cantidad', '150.00')
            ->where('mantenimiento.costos.1.cantidad', '200.50')
        );
    });

    test('edit page returns not found for a missing mantenimiento', function () {
        $this->actingAs($this->user)
            ->get('/admin/sti/mantenimientos/999999/edit')
            ->assertNotFound();
    });
});

describe('update', function () {
    test('mantenimiento can be updated with a tecnico', function () {
        $mantenimiento = Mantenimiento::factory()->create(['tecnico_id' => null]);
        $tecnico = Tecnico::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.sti.mantenimientos.update', $mantenimiento), [
                'equipo_id' => $mantenimiento->equipo_id,
                'fecha_programada' => '2026-11-15',
                'descripcion' => 'Limpieza general',
                'tecnico_id' => $tecnico->id,
            ]);

        $response->assertRedirect(route('admin.sti.mantenimientos.index'));

        expect($mantenimiento->fresh())
            ->tecnico_id->toBe($tecnico->id)
            ->descripcion->toBe('Limpieza general');
    });

    test('tecnico is required to update a mantenimiento', function () {
        $mantenimiento = Mantenimiento::factory()->create(['tecnico_id' => null, 'descripcion' => 'Original']);

        $response = $this->actingAs($this->user)
            ->from(route('admin.sti.mantenimientos.edit', $mantenimiento))
            ->put(route('admin.sti.mantenimientos.update', $mantenimiento), [
                'equipo_id' => $mantenimiento->equipo_id,
                'fecha_programada' => '2026-11-15',
                'descripcion' => 'Cambiada',
                'tecnico_id' => '',
            ]);

        $response->assertRedirect(route('admin.sti.mantenimientos.edit', $mantenimiento));
        $response->assertSessionHasErrors(['tecnico_id' => 'El técnico es obligatorio.']);

        expect($mantenimiento->fresh()->descripcion)->toBe('Original');
    });

    test('required fields show their validation messages', function () {
        $mantenimiento = Mantenimiento::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.sti.mantenimientos.update', $mantenimiento), []);

        $response->assertSessionHasErrors([
            'equipo_id' => 'El equipo es obligatorio.',
            'fecha_programada' => 'La fecha programada es obligatoria.',
            'tecnico_id' => 'El técnico es obligatorio.',
        ]);
    });
});
