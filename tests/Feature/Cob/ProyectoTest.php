<?php

use App\Models\Cob\Estimacion;
use App\Models\Obra;
use App\Models\Proyecto;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('lista proyectos', function () {
    Proyecto::factory()->count(2)->create();

    $this->actingAs($this->user)
        ->get(route('admin.cob.proyectos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/cob/proyectos/index')->has('proyectos', 2));
});

it('muestra un proyecto con sus obras', function () {
    $proyecto = Proyecto::factory()->create();
    Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base']);

    $this->actingAs($this->user)
        ->get(route('admin.cob.proyectos.show', $proyecto))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/cob/proyectos/show'));
});

it('actualiza los datos comerciales del proyecto', function () {
    $proyecto = Proyecto::factory()->create(['monto' => 100, 'anticipo' => 0]);

    $this->actingAs($this->user)
        ->put(route('admin.cob.proyectos.update', $proyecto), [
            'descripcion' => 'Nave actualizada',
            'tipo_contrato' => 'alzado',
            'monto' => 500000,
            'anticipo' => 150000,
            'garantia' => 25000,
        ])
        ->assertRedirect();

    $proyecto->refresh();
    expect($proyecto->descripcion)->toBe('Nave actualizada')
        ->and((float) $proyecto->monto)->toBe(500000.0)
        ->and((float) $proyecto->anticipo)->toBe(150000.0);
});

it('el show carga la obra base con sus relaciones comerciales', function () {
    $proyecto = Proyecto::factory()->create();
    Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base']);

    $this->actingAs($this->user)
        ->get(route('admin.cob.proyectos.show', $proyecto))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/cob/proyectos/show')
            ->has('proyecto.obra_base')
            ->has('clientes')
            ->has('documentoSecciones')
        );
});

it('crea un proyecto', function () {
    $this->actingAs($this->user)
        ->post(route('admin.cob.proyectos.store'), [
            'no' => 'PRY-1',
            'descripcion' => 'Nave industrial',
            'tipo_contrato' => 'precio_unitario',
            'monto' => 1000000,
            'anticipo' => 300000,
        ])
        ->assertRedirect();

    $proyecto = Proyecto::firstWhere('no', 'PRY-1');
    expect($proyecto)->not->toBeNull();

    // Al crear el proyecto se crea su obra base (sin partidas).
    expect($proyecto->obras()->where('tipo', 'base')->count())->toBe(1);
    $obraBase = $proyecto->obras()->first();
    expect($obraBase->partidas()->count())->toBe(0);
});

it('el backfill crea un proyecto por obra no-planta y deja la planta fuera', function () {
    $o1 = Obra::factory()->create(['es_planta' => false, 'no' => 'OB-1', 'descripcion' => 'Obra uno', 'anticipo' => 1000]);
    Obra::factory()->create(['es_planta' => false]);
    $planta = Obra::factory()->create(['es_planta' => true]);

    // Ejecuta la lógica de la migración de backfill sobre las obras recién creadas.
    (require database_path('migrations/2026_06_16_235130_backfill_proyectos_from_obras.php'))->up();

    expect(Proyecto::count())->toBe(2)
        ->and($planta->fresh()->proyecto_id)->toBeNull();

    $o1->refresh();
    expect($o1->proyecto_id)->not->toBeNull()
        ->and($o1->tipo)->toBe('base')
        ->and($o1->proyecto->no)->toBe('OB-1')
        ->and((float) $o1->proyecto->anticipo)->toBe(1000.0);
});

it('el backfill liga las estimaciones al proyecto de su obra', function () {
    $proyecto = Proyecto::factory()->create();
    $obra = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base']);
    $est = Estimacion::factory()->create(['obra_id' => $obra->id, 'proyecto_id' => null]);

    (require database_path('migrations/2026_06_17_075227_backfill_estimaciones_proyecto.php'))->up();

    expect($est->fresh()->proyecto_id)->toBe($proyecto->id)
        ->and($proyecto->estimaciones()->count())->toBe(1);
});
