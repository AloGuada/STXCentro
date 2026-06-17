<?php

use App\Models\Costos\Rubro;
use App\Models\Obra;
use App\Models\Proyecto;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('crea una obra adicional desde el proyecto con su propio presupuesto', function () {
    Rubro::factory()->count(2)->create(['ambito' => 'obra']);
    $proyecto = Proyecto::factory()->create();
    $base = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base']);

    $this->actingAs($this->user)
        ->post(route('admin.cob.proyectos.obras.store', $proyecto), [
            'no' => 'AD-1',
            'descripcion' => 'Adicional A',
            'tipo' => 'adicional',
        ])
        ->assertRedirect(route('admin.cob.proyectos.show', $proyecto));

    $sub = $proyecto->subObras()->firstOrFail();

    expect($sub->proyecto_id)->toBe($proyecto->id)
        ->and($sub->obra_padre_id)->toBe($base->id) // cuelga de la obra base (ancla)
        ->and($sub->tipo)->toBe('adicional')
        ->and($sub->estatus)->toBe('abierta')
        ->and($sub->obraRubros()->count())->toBe(2) // presupuesto propio auto-creado
        ->and($sub->partidas()->count())->toBe(0);
});

it('crea una obra normal (base) desde el proyecto sin padre', function () {
    Rubro::factory()->create(['ambito' => 'obra']);
    $proyecto = Proyecto::factory()->create();

    $this->actingAs($this->user)
        ->post(route('admin.cob.proyectos.obras.store', $proyecto), [
            'no' => 'OB-2',
            'descripcion' => 'Segunda obra',
            'tipo' => 'base',
        ])
        ->assertRedirect();

    $obra = $proyecto->obras()->where('no', 'OB-2')->firstOrFail();
    expect($obra->tipo)->toBe('base')
        ->and($obra->obra_padre_id)->toBeNull();
});

it('una obra puede tener sus propias partidas', function () {
    $proyecto = Proyecto::factory()->create();
    $obra = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'adicional']);

    $this->actingAs($this->user)
        ->post(route('admin.cob.obras.partidas.store', $obra), [
            'tipo' => 'suministro',
            'descripcion' => 'Suministro adicional',
            'monto' => 5000,
            'moneda' => 'MXN',
        ])
        ->assertRedirect();

    expect($obra->partidas()->count())->toBe(1);
});

it('actualiza no, descripción y tipo de una obra', function () {
    $proyecto = Proyecto::factory()->create();
    $obra = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base']);

    $this->actingAs($this->user)
        ->put(route('admin.cob.proyectos.obras.update', [$proyecto, $obra]), [
            'no' => 'OB-9',
            'descripcion' => 'Renombrada',
            'tipo' => 'adicional',
            'estatus' => 'cerrada',
        ])
        ->assertRedirect();

    $obra->refresh();
    expect($obra->no)->toBe('OB-9')
        ->and($obra->descripcion)->toBe('Renombrada')
        ->and($obra->tipo)->toBe('adicional')
        ->and($obra->estatus)->toBe('cerrada')
        ->and((bool) $obra->activa)->toBeFalse(); // estatus y activa sincronizados
});
