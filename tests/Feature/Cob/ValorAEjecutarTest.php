<?php

use App\Models\Cob\Comparativo;
use App\Models\Cob\Partida;
use App\Models\Obra;
use App\Models\Proyecto;
use App\Services\Cob\ValorAEjecutarService;

/**
 * Paridad con la regla de `resources/js/components/cob/calculos.ts:163-190`.
 * Si un caso de aquí cambia, el frontend debe cambiar igual.
 */
beforeEach(function () {
    $this->servicio = app(ValorAEjecutarService::class);
    $this->proyecto = Proyecto::factory()->create();
});

function obraDelProyecto(Proyecto $proyecto, string $tipoContrato = 'precio_unitario', string $tipo = 'base'): Obra
{
    return Obra::factory()->create([
        'proyecto_id' => $proyecto->id,
        'tipo' => $tipo,
        'tipo_contrato' => $tipoContrato,
    ]);
}

function partidaDe(Obra $obra, float $monto): Partida
{
    return Partida::factory()->create(['obra_id' => $obra->id, 'monto' => $monto]);
}

it('suma las partidas cuando el proyecto no tiene comparativos', function () {
    $obra = obraDelProyecto($this->proyecto);
    partidaDe($obra, 100000);
    partidaDe($obra, 50000);

    expect($this->servicio->paraProyecto($this->proyecto))->toBe(150000.0);
});

it('usa el comparativo en una obra a precio unitario', function () {
    $obra = obraDelProyecto($this->proyecto, 'precio_unitario');
    partidaDe($obra, 100000);
    Comparativo::factory()->create([
        'proyecto_id' => $this->proyecto->id,
        'obra_id' => $obra->id,
        'monto_impacto' => 175000,
    ]);

    expect($this->servicio->paraProyecto($this->proyecto))->toBe(175000.0);
});

it('gana el comparativo de mayor id, no el de fecha más reciente', function () {
    $obra = obraDelProyecto($this->proyecto, 'precio_unitario');

    Comparativo::factory()->create([
        'proyecto_id' => $this->proyecto->id,
        'obra_id' => $obra->id,
        'monto_impacto' => 100000,
        'fecha_identificacion' => '2026-12-31',
    ]);
    Comparativo::factory()->create([
        'proyecto_id' => $this->proyecto->id,
        'obra_id' => $obra->id,
        'monto_impacto' => 200000,
        'fecha_identificacion' => '2020-01-01',
    ]);

    expect($this->servicio->paraProyecto($this->proyecto))->toBe(200000.0);
});

it('aporta cero si la obra unitaria no tiene comparativo pero otra del proyecto sí', function () {
    $conComparativo = obraDelProyecto($this->proyecto, 'precio_unitario');
    $sinComparativo = obraDelProyecto($this->proyecto, 'precio_unitario', 'adicional');

    partidaDe($sinComparativo, 999999);
    Comparativo::factory()->create([
        'proyecto_id' => $this->proyecto->id,
        'obra_id' => $conComparativo->id,
        'monto_impacto' => 300000,
    ]);

    expect($this->servicio->paraProyecto($this->proyecto))->toBe(300000.0);
});

it('ignora el comparativo en una obra a precio alzado', function () {
    $obra = obraDelProyecto($this->proyecto, 'precio_alzado');
    partidaDe($obra, 80000);
    Comparativo::factory()->create([
        'proyecto_id' => $this->proyecto->id,
        'obra_id' => $obra->id,
        'monto_impacto' => 500000,
    ]);

    expect($this->servicio->paraProyecto($this->proyecto))->toBe(80000.0);
});

it('ignora un comparativo sin obra y no activa la regla', function () {
    $obra = obraDelProyecto($this->proyecto, 'precio_unitario');
    partidaDe($obra, 120000);
    Comparativo::factory()->create([
        'proyecto_id' => $this->proyecto->id,
        'obra_id' => null,
        'monto_impacto' => 999999,
    ]);

    expect($this->servicio->paraProyecto($this->proyecto))->toBe(120000.0);
});

it('mezcla obra base unitaria con comparativo y adicional alzada con partidas', function () {
    $base = obraDelProyecto($this->proyecto, 'precio_unitario');
    $adicional = obraDelProyecto($this->proyecto, 'precio_alzado', 'adicional');

    partidaDe($base, 111111);
    partidaDe($adicional, 40000);
    Comparativo::factory()->create([
        'proyecto_id' => $this->proyecto->id,
        'obra_id' => $base->id,
        'monto_impacto' => 250000,
    ]);

    expect($this->servicio->paraProyecto($this->proyecto))->toBe(290000.0);
});

it('calcula varios proyectos sin caer en N+1', function () {
    $proyectos = Proyecto::factory()->count(5)->create();

    foreach ($proyectos as $proyecto) {
        $obra = obraDelProyecto($proyecto, 'precio_alzado');
        partidaDe($obra, 10000);
    }

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $valores = $this->servicio->paraProyectos(Proyecto::whereIn('id', $proyectos->pluck('id'))->get());

    expect($valores)->toHaveCount(5);
    expect(array_values($valores))->each->toBe(10000.0);
    // 1 query por cada relación cargada (obras, partidas, comparativos), no por proyecto.
    expect($queries)->toBeLessThanOrEqual(4);
});
