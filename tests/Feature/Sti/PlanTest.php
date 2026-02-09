<?php

use App\Models\Sti\Equipo;
use App\Models\Sti\Mantenimiento;
use App\Models\Sti\Plan;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin planes', function () {
    test('index page can be rendered', function () {
        Plan::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.planes.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/sti/planes/index')
            ->has('planes.data', 3)
        );
    });

    test('index does not include equipos prop', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.planes.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->missing('equipos')
        );
    });

    test('create page can be rendered without equipos', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.planes.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/sti/planes/create')
            ->missing('equipos')
        );
    });

    test('plan can be stored without equipo_id', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.planes.store'), [
                'descripcion' => 'Plan de mantenimiento preventivo',
                'periodicidad' => 30,

                'activo' => true,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('sti_planes', [
            'descripcion' => 'Plan de mantenimiento preventivo',
            'periodicidad' => 30,
        ]);
    });

    test('plan store does not require equipo_id', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.planes.store'), [
                'descripcion' => 'Plan sin equipo',
                'periodicidad' => 60,

                'activo' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    });

    test('plan store creates checks', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.planes.store'), [
                'descripcion' => 'Plan con checks',
                'periodicidad' => 30,

                'activo' => true,
                'checks' => [
                    ['descripcion' => 'Check 1'],
                    ['descripcion' => 'Check 2'],
                ],
            ]);

        $response->assertRedirect();

        $plan = Plan::where('descripcion', 'Plan con checks')->first();
        expect($plan->checks)->toHaveCount(2);
    });

    test('edit page can be rendered without equipos', function () {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.sti.planes.edit', $plan));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/sti/planes/edit')
            ->has('plan')
            ->missing('equipos')
        );
    });

    test('plan can be updated', function () {
        $plan = Plan::factory()->create(['descripcion' => 'Old']);

        $response = $this->actingAs($this->user)
            ->put(route('admin.sti.planes.update', $plan), [
                'descripcion' => 'Updated',
                'periodicidad' => 60,

                'activo' => true,
            ]);

        $response->assertRedirect(route('admin.sti.planes.index'));

        $this->assertDatabaseHas('sti_planes', [
            'id' => $plan->id,
            'descripcion' => 'Updated',
        ]);
    });

    test('plan can be deleted when no completed mantenimientos', function () {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.sti.planes.destroy', $plan));

        $response->assertRedirect(route('admin.sti.planes.index'));
        $this->assertDatabaseMissing('sti_planes', ['id' => $plan->id]);
    });

    test('plan cannot be deleted with completed mantenimientos', function () {
        $plan = Plan::factory()->create();
        $equipo = Equipo::factory()->create();

        Mantenimiento::create([
            'equipo_id' => $equipo->id,
            'plan_id' => $plan->id,
            'fecha_programada' => '2026-03-01',
            'descripcion' => 'Test',
            'status' => 'realizado',
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('admin.sti.planes.destroy', $plan));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('sti_planes', ['id' => $plan->id]);
    });

    test('validation requires descripcion', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.planes.store'), [
                'periodicidad' => 30,

            ]);

        $response->assertSessionHasErrors(['descripcion']);
    });
});
