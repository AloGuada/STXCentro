<?php

use App\Models\Sti\Equipo;
use App\Models\Sti\Mantenimiento;
use App\Models\Sti\Plan;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('programacion page', function () {
    test('programacion page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.mantenimientos.programacion'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/sti/mantenimientos/programacion')
            ->has('equipos')
            ->has('planes')
            ->has('year')
        );
    });

    test('programacion page respects year parameter', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.mantenimientos.programacion', ['year' => 2027]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('year', 2027)
        );
    });

    test('programacion shows equipment with mantenimiento counts', function () {
        $equipo = Equipo::factory()->create();
        $plan = Plan::factory()->create(['fecha_inicial' => '2026-01-01', 'periodicidad' => 30]);

        Mantenimiento::create([
            'equipo_id' => $equipo->id,
            'plan_id' => $plan->id,
            'fecha_programada' => '2026-03-01',
            'descripcion' => 'Test',
            'status' => 'pendiente',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.mantenimientos.programacion', ['year' => 2026]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('equipos', 1)
            ->where('equipos.0.mantenimientos_count', 1)
        );
    });

    test('only active planes are returned', function () {
        Plan::factory()->create(['activo' => true]);
        Plan::factory()->create(['activo' => false]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.mantenimientos.programacion'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('planes', 1)
        );
    });
});

describe('generar mantenimientos', function () {
    test('can generate mantenimientos for multiple equipos', function () {
        $plan = Plan::factory()->create([
            'fecha_inicial' => '2026-01-01',
            'periodicidad' => 90,
            'activo' => true,
        ]);
        $plan->checks()->create(['descripcion' => 'Check 1', 'orden' => 0]);

        $equipo1 = Equipo::factory()->create();
        $equipo2 = Equipo::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.mantenimientos.generar'), [
                'plan_id' => $plan->id,
                'equipo_ids' => [$equipo1->id, $equipo2->id],
                'year' => 2026,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Each equipo should have mantenimientos generated
        expect(Mantenimiento::where('equipo_id', $equipo1->id)->count())->toBeGreaterThan(0);
        expect(Mantenimiento::where('equipo_id', $equipo2->id)->count())->toBeGreaterThan(0);

        // Both should reference the same plan
        expect(Mantenimiento::where('plan_id', $plan->id)->count())->toBeGreaterThan(0);
    });

    test('generated mantenimientos include check ejecuciones', function () {
        $plan = Plan::factory()->create([
            'fecha_inicial' => '2026-06-01',
            'periodicidad' => 180,
            'activo' => true,
        ]);
        $plan->checks()->create(['descripcion' => 'Check A', 'orden' => 0]);
        $plan->checks()->create(['descripcion' => 'Check B', 'orden' => 1]);

        $equipo = Equipo::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.sti.mantenimientos.generar'), [
                'plan_id' => $plan->id,
                'equipo_ids' => [$equipo->id],
                'year' => 2026,
            ]);

        $mantenimiento = Mantenimiento::where('equipo_id', $equipo->id)->first();
        expect($mantenimiento)->not->toBeNull();
        expect($mantenimiento->checkEjecuciones)->toHaveCount(2);
    });

    test('does not duplicate existing mantenimientos', function () {
        $plan = Plan::factory()->create([
            'fecha_inicial' => '2026-01-01',
            'periodicidad' => 90,
            'activo' => true,
        ]);

        $equipo = Equipo::factory()->create();

        // Generate first time
        $this->actingAs($this->user)
            ->post(route('admin.sti.mantenimientos.generar'), [
                'plan_id' => $plan->id,
                'equipo_ids' => [$equipo->id],
                'year' => 2026,
            ]);

        $countAfterFirst = Mantenimiento::where('equipo_id', $equipo->id)->count();

        // Generate again
        $this->actingAs($this->user)
            ->post(route('admin.sti.mantenimientos.generar'), [
                'plan_id' => $plan->id,
                'equipo_ids' => [$equipo->id],
                'year' => 2026,
            ]);

        $countAfterSecond = Mantenimiento::where('equipo_id', $equipo->id)->count();

        expect($countAfterSecond)->toBe($countAfterFirst);
    });

    test('validation requires plan_id and equipo_ids', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.mantenimientos.generar'), [
                'year' => 2026,
            ]);

        $response->assertSessionHasErrors(['plan_id', 'equipo_ids']);
    });

    test('validation requires at least one equipo', function () {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.mantenimientos.generar'), [
                'plan_id' => $plan->id,
                'equipo_ids' => [],
                'year' => 2026,
            ]);

        $response->assertSessionHasErrors(['equipo_ids']);
    });
});
