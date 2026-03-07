<?php

use App\Models\Cliente;
use App\Models\Cob\Disputa;
use App\Models\Cob\Estimacion;
use App\Models\Cob\EstimacionPago;
use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->cliente = Cliente::factory()->create();
    $this->obra = Obra::factory()->create([
        'cliente_id' => $this->cliente->id,
        'activa' => true,
        'monto' => 1000000,
        'anticipo' => 300000,
        'porcentaje_obra' => 50,
    ]);
});

describe('cob dashboard', function () {
    test('dashboard loads successfully', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.cob.dashboard.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/cob/dashboard/index')
            ->has('obras')
            ->has('dsoPorObra')
            ->has('retencionesPorTipo')
            ->has('disputas')
        );
    });

    test('dashboard includes active obras with client', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.cob.dashboard.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('obras', 1)
        );
    });

    test('dashboard excludes inactive obras', function () {
        $this->obra->update(['activa' => false]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.cob.dashboard.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('obras', 0)
        );
    });

    test('dashboard includes disputas with dias_abierta', function () {
        Disputa::factory()->create([
            'obra_id' => $this->obra->id,
            'estado' => 'en_proceso',
            'fecha_inicio' => now()->subDays(10),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.cob.dashboard.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('disputas', 1)
            ->where('disputas.0.estado', 'en_proceso')
            ->where('disputas.0.dias_abierta', 10)
        );
    });

    test('dashboard calculates dso when pagos exist', function () {
        $estimacion = Estimacion::factory()->create([
            'obra_id' => $this->obra->id,
            'fecha_emision' => '2026-01-01',
            'estado' => 'pagado',
            'monto_estimado' => 100000,
        ]);

        EstimacionPago::factory()->create([
            'estimacion_id' => $estimacion->id,
            'fecha_pago' => '2026-01-31',
            'monto_pagado' => 100000,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.cob.dashboard.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('dsoPorObra', 1)
            ->where('dsoPorObra.0.dias_promedio', 30)
        );
    });
});
