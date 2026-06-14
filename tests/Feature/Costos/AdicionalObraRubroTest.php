<?php

use App\Models\Costos\Rubro;
use App\Models\Obra;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('marcar una partida como adicional crea su presupuesto con todos los centros de costo', function () {
    $rubroA = Rubro::factory()->create(['ambito' => 'obra']);
    Rubro::factory()->create(['ambito' => 'obra']);
    $obra = Obra::factory()->create();

    $this->actingAs($this->user)
        ->post(route('admin.cob.obras.partidas.store', $obra), [
            'tipo' => 'suministro',
            'descripcion' => 'Trabajo adicional',
            'monto' => 1000,
            'moneda' => 'MXN',
            'es_adicional' => true,
        ])
        ->assertRedirect();

    $partida = $obra->partidas()->where('es_adicional', true)->firstOrFail();

    // Numerado adX por orden de creación.
    expect($partida->numero_adicional)->toBe(1);

    // El adicional recibe su propio set de rubros (uno por cada rubro de obra)...
    expect($partida->obraRubros()->count())->toBe(2);
    $this->assertDatabaseHas('costos_obra_rubros', [
        'obra_id' => $obra->id,
        'adicional_partida_id' => $partida->id,
        'rubro_id' => $rubroA->id,
    ]);

    // ...sin inflar el presupuesto base de la obra (scoped a adicional NULL).
    expect($obra->obraRubros()->count())->toBe(2);
});

test('un segundo adicional toma el siguiente número correlativo', function () {
    Rubro::factory()->create(['ambito' => 'obra']);
    $obra = Obra::factory()->create();

    foreach (['Adicional uno', 'Adicional dos'] as $descripcion) {
        $this->actingAs($this->user)
            ->post(route('admin.cob.obras.partidas.store', $obra), [
                'tipo' => 'suministro',
                'descripcion' => $descripcion,
                'monto' => 500,
                'moneda' => 'MXN',
                'es_adicional' => true,
            ])
            ->assertRedirect();
    }

    $numeros = $obra->partidas()->where('es_adicional', true)->orderBy('id')->pluck('numero_adicional');
    expect($numeros->all())->toBe([1, 2]);
});
