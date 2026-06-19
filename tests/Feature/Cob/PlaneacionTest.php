<?php

use App\Models\Cob\ObraEtapa;
use App\Models\Cob\PlanCobro;
use App\Models\Obra;
use App\Models\Proyecto;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('genera el plan de cobro (N estimaciones × D días) con fechas contiguas', function () {
    $proyecto = Proyecto::factory()->create();

    $this->actingAs($this->user)
        ->put(route('admin.cob.proyectos.plan-cobro', $proyecto), [
            'fecha_inicio_plan' => '2026-01-01',
            'numero' => 3,
            'dias' => 10,
        ])
        ->assertRedirect();

    $plan = $proyecto->planCobro()->get();

    expect($plan)->toHaveCount(3)
        ->and($plan[0]->fecha_inicio_plan->toDateString())->toBe('2026-01-01')
        ->and($plan[0]->fecha_fin_plan->toDateString())->toBe('2026-01-11')
        ->and($plan[1]->fecha_inicio_plan->toDateString())->toBe('2026-01-11')
        ->and($plan[2]->fecha_fin_plan->toDateString())->toBe('2026-01-31');

    expect($proyecto->fresh()->fecha_inicio_plan->toDateString())->toBe('2026-01-01');
});

it('regenerar el plan reemplaza el anterior', function () {
    $proyecto = Proyecto::factory()->create();

    $this->actingAs($this->user)->put(route('admin.cob.proyectos.plan-cobro', $proyecto), [
        'fecha_inicio_plan' => '2026-01-01', 'numero' => 5, 'dias' => 10,
    ]);
    expect($proyecto->planCobro()->count())->toBe(5);

    $this->actingAs($this->user)->put(route('admin.cob.proyectos.plan-cobro', $proyecto), [
        'fecha_inicio_plan' => '2026-02-01', 'numero' => 2, 'dias' => 15,
    ]);
    expect($proyecto->planCobro()->count())->toBe(2);
});

it('valida número y días positivos', function () {
    $proyecto = Proyecto::factory()->create();

    $this->actingAs($this->user)
        ->put(route('admin.cob.proyectos.plan-cobro', $proyecto), [
            'fecha_inicio_plan' => '2026-01-01', 'numero' => 0, 'dias' => 0,
        ])
        ->assertSessionHasErrors(['numero', 'dias']);
});

it('crea una etapa PMO (obra + descripción + fechas) desde el modal', function () {
    $proyecto = Proyecto::factory()->create();
    $obra = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base']);

    $this->actingAs($this->user)
        ->post(route('admin.cob.proyectos.etapas.store', $proyecto), [
            'obra_id' => $obra->id,
            'descripcion' => 'Fabricación',
            'fecha_inicio_plan' => '2026-01-01',
            'fecha_fin_plan' => '2026-02-15',
        ])
        ->assertRedirect();

    $etapa = $obra->etapasPmo()->first();
    expect($etapa->descripcion)->toBe('Fabricación')
        ->and($etapa->fecha_inicio_plan->toDateString())->toBe('2026-01-01');
});

it('rechaza crear una etapa para una obra de otro proyecto', function () {
    $proyecto = Proyecto::factory()->create();
    $ajena = Obra::factory()->create(['tipo' => 'base']); // de otro proyecto

    $this->actingAs($this->user)
        ->post(route('admin.cob.proyectos.etapas.store', $proyecto), [
            'obra_id' => $ajena->id,
            'descripcion' => 'X',
            'fecha_inicio_plan' => '2026-01-01',
            'fecha_fin_plan' => '2026-02-01',
        ])
        ->assertNotFound();

    expect(ObraEtapa::where('obra_id', $ajena->id)->count())->toBe(0);
});

it('guarda los ajustes: plan por orden y etapas por id', function () {
    $proyecto = Proyecto::factory()->create();
    $obra = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base']);
    $etapa = ObraEtapa::create([
        'obra_id' => $obra->id, 'descripcion' => 'Montaje',
        'fecha_inicio_plan' => '2026-01-01', 'fecha_fin_plan' => '2026-01-10',
    ]);
    PlanCobro::create([
        'proyecto_id' => $proyecto->id, 'orden' => 1, 'dias' => 10,
        'fecha_inicio_plan' => '2026-01-01', 'fecha_fin_plan' => '2026-01-11',
    ]);

    $this->actingAs($this->user)
        ->put(route('admin.cob.proyectos.planeacion', $proyecto), [
            'plan' => [['orden' => 1, 'fecha_inicio_plan' => '2026-01-05', 'fecha_fin_plan' => '2026-01-25']],
            'etapas' => [['id' => $etapa->id, 'fecha_inicio_plan' => '2026-02-01', 'fecha_fin_plan' => '2026-03-01']],
        ])
        ->assertRedirect();

    expect($proyecto->planCobro()->first()->fecha_fin_plan->toDateString())->toBe('2026-01-25')
        ->and($proyecto->planCobro()->first()->dias)->toBe(20)
        ->and($etapa->fresh()->fecha_inicio_plan->toDateString())->toBe('2026-02-01');
});

it('elimina una etapa', function () {
    $proyecto = Proyecto::factory()->create();
    $obra = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base']);
    $etapa = ObraEtapa::create([
        'obra_id' => $obra->id, 'descripcion' => 'Suministro',
        'fecha_inicio_plan' => '2026-01-01', 'fecha_fin_plan' => '2026-01-10',
    ]);

    $this->actingAs($this->user)
        ->delete(route('admin.cob.proyectos.etapas.destroy', [$proyecto, $etapa]))
        ->assertRedirect();

    expect(ObraEtapa::find($etapa->id))->toBeNull();
});

it('el show del proyecto carga plan de cobro y etapas PMO', function () {
    $proyecto = Proyecto::factory()->create();
    Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base']);

    $this->actingAs($this->user)
        ->get(route('admin.cob.proyectos.show', $proyecto))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/cob/proyectos/show')
            ->has('proyecto.plan_cobro')
            ->has('proyecto.obras.0.etapas_pmo')
        );
});
