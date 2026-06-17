<?php

use App\Models\Cob\Partida;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Rubro;
use App\Models\Obra;
use App\Models\Proyecto;

function correrConversion(): void
{
    (require database_path('migrations/2026_06_17_152532_convert_adicionales_to_subobras.php'))->up();
}

it('convierte una partida adicional en sub-obra y re-apunta su presupuesto', function () {
    $rubros = Rubro::factory()->count(2)->create(['ambito' => 'obra']);
    $proyecto = Proyecto::factory()->create();
    $base = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base', 'no' => 'OB-9']);

    // Estado legacy: partida adicional + sus obra_rubros con adicional_partida_id.
    $partida = Partida::create([
        'obra_id' => $base->id,
        'tipo' => 'suministro',
        'descripcion' => 'Adicional A',
        'monto' => 1000,
        'moneda' => 'MXN',
        'es_adicional' => true,
        'numero_adicional' => 1,
        'estatus' => 'abierta',
    ]);
    foreach ($rubros as $r) {
        ObraRubro::create([
            'obra_id' => $base->id,
            'rubro_id' => $r->id,
            'adicional_partida_id' => $partida->id,
            'presupuestado' => 500,
            'acumulado' => 100,
        ]);
    }

    correrConversion();

    $sub = $base->subObras()->firstOrFail();
    expect($sub->tipo)->toBe('adicional')
        ->and($sub->proyecto_id)->toBe($proyecto->id)
        ->and($sub->obra_padre_id)->toBe($base->id)
        ->and($sub->no)->toBe('OB-9-ad1');

    // Presupuesto re-apuntado a la sub-obra, conservando montos (neto-cero).
    expect($sub->obraRubros()->count())->toBe(2)
        ->and((float) $sub->obraRubros()->sum('presupuestado'))->toBe(1000.0)
        ->and((float) $sub->obraRubros()->sum('acumulado'))->toBe(200.0);

    // La partida queda como partida normal de la sub-obra.
    $partida->refresh();
    expect($partida->obra_id)->toBe($sub->id)
        ->and((bool) $partida->es_adicional)->toBeFalse();

    // La obra base conserva su propio presupuesto (los rubros del booted).
    expect($base->obraRubros()->count())->toBe(2);

    // Ya no quedan obra_rubros con adicional_partida_id.
    expect(ObraRubro::whereNotNull('adicional_partida_id')->count())->toBe(0);
});

it('es idempotente (re-correr no duplica sub-obras)', function () {
    Rubro::factory()->create(['ambito' => 'obra']);
    $proyecto = Proyecto::factory()->create();
    $base = Obra::factory()->create(['proyecto_id' => $proyecto->id, 'tipo' => 'base']);
    $partida = Partida::create([
        'obra_id' => $base->id, 'tipo' => 'montaje', 'descripcion' => 'Ad', 'monto' => 0,
        'moneda' => 'MXN', 'es_adicional' => true, 'numero_adicional' => 1, 'estatus' => 'abierta',
    ]);
    ObraRubro::create(['obra_id' => $base->id, 'rubro_id' => Rubro::first()->id, 'adicional_partida_id' => $partida->id, 'presupuestado' => 0, 'acumulado' => 0]);

    correrConversion();
    correrConversion();

    expect($base->subObras()->count())->toBe(1);
});
