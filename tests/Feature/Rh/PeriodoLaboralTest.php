<?php

use App\Models\Rh\PeriodoLaboral;
use App\Models\Rh\Persona;
use App\Models\Rh\Puesto;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['rh.periodos-laborales.ver', 'rh.periodos-laborales.crear', 'rh.periodos-laborales.editar', 'rh.periodos-laborales.eliminar', 'rh.onboarding.crear'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $this->user->givePermissionTo(['rh.periodos-laborales.ver', 'rh.periodos-laborales.crear', 'rh.periodos-laborales.editar', 'rh.periodos-laborales.eliminar', 'rh.onboarding.crear']);
});

describe('admin rh periodos laborales', function () {
    test('index page can be rendered', function () {
        PeriodoLaboral::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.rh.periodos-laborales.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/rh/periodos-laborales/index')
            ->has('periodos.data', 3)
        );
    });

    test('periodo laboral can be stored', function () {
        $persona = Persona::factory()->create();
        $puesto = Puesto::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.periodos-laborales.store'), [
                'persona_id' => $persona->id,
                'puesto_id' => $puesto->id,
                'fecha_inicio' => '2026-01-15',
                'estado' => 'activo',
                'salario_diario' => 800,
                'sueldo_mensual' => 24000,
                'tipo_contrato' => 'indefinido',
            ]);

        $response->assertRedirect(route('admin.rh.periodos-laborales.index'));
        $this->assertDatabaseHas('rh_periodos_laborales', [
            'persona_id' => $persona->id,
            'puesto_id' => $puesto->id,
        ]);
    });

    test('periodo laboral can be terminated', function () {
        $periodo = PeriodoLaboral::factory()->create(['estado' => 'activo']);

        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.periodos-laborales.terminar', $periodo));

        $response->assertRedirect();
        $periodo->refresh();
        expect($periodo->estado)->toBe('baja');
        expect($periodo->fecha_fin)->not->toBeNull();
    });

    test('onboarding can be created for periodo', function () {
        $periodo = PeriodoLaboral::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.periodos-laborales.onboarding', $periodo));

        $response->assertRedirect();
        $this->assertDatabaseHas('rh_onboarding', [
            'periodo_id' => $periodo->id,
        ]);
    });

    test('gafete pdf can be downloaded', function () {
        $periodo = PeriodoLaboral::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.rh.periodos-laborales.gafete-pdf', $periodo));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    });

    test('tarjeta pdf can be downloaded', function () {
        $periodo = PeriodoLaboral::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.rh.periodos-laborales.tarjeta-pdf', $periodo));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    });

    test('duplicate onboarding is rejected', function () {
        $periodo = PeriodoLaboral::factory()->create();
        $periodo->onboarding()->create(['fecha_inicio' => now(), 'progreso' => 0]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.rh.periodos-laborales.onboarding', $periodo));

        $response->assertSessionHasErrors(['onboarding']);
    });
});
