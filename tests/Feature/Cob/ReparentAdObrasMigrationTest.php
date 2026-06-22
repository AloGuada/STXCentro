<?php

use App\Models\Obra;
use App\Models\Proyecto;

function correrReparent(): void
{
    (require database_path('migrations/2026_06_22_100002_reparent_ad_obras_into_parent_proyecto.php'))->up();
}

it('reacomoda una obra "X AD#" dentro del proyecto del padre y borra el huérfano', function () {
    $proyPadre = Proyecto::factory()->create();
    Obra::factory()->create(['no' => 'S2502-05', 'tipo' => 'base', 'proyecto_id' => $proyPadre->id, 'es_planta' => false]);

    $proyHuerfano = Proyecto::factory()->create();
    $adicional = Obra::factory()->create(['no' => 'S2502-05 AD3', 'tipo' => 'base', 'proyecto_id' => $proyHuerfano->id, 'es_planta' => false]);

    correrReparent();

    $adicional->refresh();
    expect($adicional->tipo)->toBe('adicional')
        ->and($adicional->proyecto_id)->toBe($proyPadre->id)
        ->and(Proyecto::find($proyHuerfano->id))->toBeNull(); // proyecto huérfano eliminado
});

it('deja la obra AD con su proyecto propio si no existe el padre', function () {
    $proy = Proyecto::factory()->create();
    $adicional = Obra::factory()->create(['no' => 'X999 AD1', 'tipo' => 'base', 'proyecto_id' => $proy->id, 'es_planta' => false]);

    correrReparent();

    $adicional->refresh();
    expect($adicional->tipo)->toBe('base')
        ->and($adicional->proyecto_id)->toBe($proy->id)
        ->and(Proyecto::find($proy->id))->not->toBeNull();
});

it('agrupa varias adicionales del mismo padre en su proyecto', function () {
    $proyPadre = Proyecto::factory()->create();
    Obra::factory()->create(['no' => 'AB-1', 'tipo' => 'base', 'proyecto_id' => $proyPadre->id, 'es_planta' => false]);

    foreach (['AB-1 AD1', 'AB-1 AD2'] as $no) {
        Obra::factory()->create(['no' => $no, 'tipo' => 'base', 'proyecto_id' => Proyecto::factory()->create()->id, 'es_planta' => false]);
    }

    correrReparent();

    $subObras = Obra::where('proyecto_id', $proyPadre->id)->where('tipo', 'adicional')->pluck('no')->sort()->values()->all();
    expect($subObras)->toBe(['AB-1 AD1', 'AB-1 AD2']);
});

it('es idempotente (re-correr no cambia nada)', function () {
    $proyPadre = Proyecto::factory()->create();
    Obra::factory()->create(['no' => 'CD-9', 'tipo' => 'base', 'proyecto_id' => $proyPadre->id, 'es_planta' => false]);
    Obra::factory()->create(['no' => 'CD-9 AD1', 'tipo' => 'base', 'proyecto_id' => Proyecto::factory()->create()->id, 'es_planta' => false]);

    correrReparent();
    correrReparent();

    expect(Obra::where('no', 'CD-9 AD1')->value('proyecto_id'))->toBe($proyPadre->id)
        ->and(Obra::where('no', 'CD-9 AD1')->value('tipo'))->toBe('adicional');
});

it('no toca obras normales que no son adicionales', function () {
    $proy = Proyecto::factory()->create();
    Obra::factory()->create(['no' => 'NAVE-100', 'tipo' => 'base', 'proyecto_id' => $proy->id, 'es_planta' => false]);

    correrReparent();

    expect(Obra::where('no', 'NAVE-100')->value('tipo'))->toBe('base');
});
