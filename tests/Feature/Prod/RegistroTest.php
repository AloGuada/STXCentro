<?php

use App\Models\Concepto;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Registro;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin registros', function () {
    test('index page can be rendered', function () {
        Registro::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.registros.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/registros/index')
            ->has('registros.data', 3)
        );
    });

    test('index can filter by grupo trabajo', function () {
        $grupo = GrupoTrabajo::factory()->create();
        Registro::factory()->count(2)->create(['grupo_trabajo_id' => $grupo->id]);
        Registro::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.registros.index', ['grupo_trabajo_id' => $grupo->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('registros.data', 2)
        );
    });

    test('index can filter by date range', function () {
        Registro::factory()->create(['fecha' => '2026-01-15']);
        Registro::factory()->create(['fecha' => '2026-01-20']);
        Registro::factory()->create(['fecha' => '2026-02-01']);

        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.registros.index', [
                'fecha_inicio' => '2026-01-10',
                'fecha_fin' => '2026-01-25',
            ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('registros.data', 2)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.prod.registros.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/prod/registros/create')
            ->has('conceptos')
            ->has('gruposTrabajo')
        );
    });

    test('registro can be stored', function () {
        $concepto = Concepto::factory()->create();
        $grupo = GrupoTrabajo::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.registros.store'), [
                'fecha' => '2026-02-10',
                'concepto_id' => $concepto->id,
                'grupo_trabajo_id' => $grupo->id,
                'cantidad' => 50,
            ]);

        $response->assertRedirect(route('admin.prod.registros.index'));

        $this->assertDatabaseHas('prod_registros', [
            'concepto_id' => $concepto->id,
            'grupo_trabajo_id' => $grupo->id,
            'cantidad' => 50,
        ]);
        $registro = Registro::first();
        expect($registro->fecha->format('Y-m-d'))->toBe('2026-02-10');
    });

    test('registro can be deleted', function () {
        $registro = Registro::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.prod.registros.destroy', $registro));

        $response->assertRedirect(route('admin.prod.registros.index'));
        $this->assertDatabaseMissing('prod_registros', ['id' => $registro->id]);
    });

    test('validation requires all fields', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.prod.registros.store'), []);

        $response->assertSessionHasErrors(['fecha', 'concepto_id', 'grupo_trabajo_id', 'cantidad']);
    });
});
