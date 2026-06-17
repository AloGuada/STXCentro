<?php

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

    $this->assertDatabaseHas('proyectos', [
        'no' => 'PRY-1',
        'descripcion' => 'Nave industrial',
        'estatus' => 'abierta',
    ]);
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
