<?php

use App\Models\Costos\Rubro;
use App\Models\Obra;
use App\Models\Proyecto;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('crea una sub-obra desde la obra base con su propio presupuesto', function () {
    Rubro::factory()->count(2)->create(['ambito' => 'obra']);
    $proyecto = Proyecto::factory()->create();
    $base = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base']);

    $this->actingAs($this->user)
        ->post(route('admin.cob.obras.sub-obras.store', $base), [
            'no' => 'AD-1',
            'descripcion' => 'Adicional A',
        ])
        ->assertRedirect();

    $sub = $base->subObras()->firstOrFail();

    expect($sub->proyecto_id)->toBe($proyecto->id)
        ->and($sub->obra_padre_id)->toBe($base->id)
        ->and($sub->tipo)->toBe('adicional')
        ->and($sub->estatus)->toBe('abierta')
        ->and($sub->obraRubros()->count())->toBe(2) // presupuesto propio auto-creado
        ->and($sub->partidas()->count())->toBe(0);   // sin partidas (pendientes)

    expect($proyecto->subObras()->count())->toBe(1);
});

it('una sub-obra puede tener sus propias partidas', function () {
    $proyecto = Proyecto::factory()->create();
    $base = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base']);
    $sub = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'obra_padre_id' => $base->id, 'tipo' => 'adicional']);

    $this->actingAs($this->user)
        ->post(route('admin.cob.obras.partidas.store', $sub), [
            'tipo' => 'suministro',
            'descripcion' => 'Suministro adicional',
            'monto' => 5000,
            'moneda' => 'MXN',
        ])
        ->assertRedirect();

    expect($sub->partidas()->count())->toBe(1);
});

it('no permite crear sub-obras sobre una sub-obra', function () {
    $proyecto = Proyecto::factory()->create();
    $base = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base']);
    $sub = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'obra_padre_id' => $base->id, 'tipo' => 'adicional']);

    $this->actingAs($this->user)
        ->post(route('admin.cob.obras.sub-obras.store', $sub), ['no' => 'X', 'descripcion' => 'Y'])
        ->assertStatus(422);
});
