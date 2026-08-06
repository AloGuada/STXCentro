<?php

use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Pieza;
use App\Models\User;
use App\Services\Prod\AvanceDePiezas;
use Illuminate\Support\Facades\DB;

/**
 * El costo de calcular el avance no debe crecer con el número de piezas.
 *
 * Estos tests cuentan consultas, no milisegundos: un perfil manual no deja
 * rastro en el repo y el N+1 se vuelve a colar en el siguiente refactor. Los
 * topes son holgados a propósito — lo que se vigila es que el número no sea
 * proporcional a las piezas, no cuadrar un número exacto.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->grupo = GrupoTrabajo::factory()->create();
    $this->soldadura = proceso();
    $this->destajo = Destajo::factory()->create([
        'fecha_inicio' => '2026-02-03',
        'fecha_fin' => '2026-02-09',
    ]);
});

/** @return array{0: mixed, 1: int} lo que devuelva el closure y las consultas que costó */
function contandoConsultas(callable $fn): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $resultado = $fn();
    $consultas = count(DB::getQueryLog());

    DB::disableQueryLog();
    DB::flushQueryLog();

    return [$resultado, $consultas];
}

test('decorar no consulta una vez por pieza', function () {
    $marca = marcaConPiezas(30);
    obraPagaProcesos($marca->obra_id);

    [, $consultas] = contandoConsultas(
        fn () => app(AvanceDePiezas::class)->decorar($marca->piezas, [$this->soldadura->id]),
    );

    // Con el N+1 esto rondaba las 30 consultas sólo para resolver la obra.
    expect($consultas)->toBeLessThan(10);
});

test('el costo de decorar no crece con el número de piezas', function () {
    $pocas = marcaConPiezas(5);
    obraPagaProcesos($pocas->obra_id);

    [, $conPocas] = contandoConsultas(
        fn () => app(AvanceDePiezas::class)->decorar($pocas->piezas, [$this->soldadura->id]),
    );

    $muchas = marcaConPiezas(40);
    obraPagaProcesos($muchas->obra_id);

    [, $conMuchas] = contandoConsultas(
        fn () => app(AvanceDePiezas::class)->decorar($muchas->piezas, [$this->soldadura->id]),
    );

    // Ocho veces más piezas, el mismo número de consultas.
    expect($conMuchas)->toBe($conPocas);
});

test('piezas de varias obras cuestan una carga por obra, no una por pieza', function () {
    $una = marcaConPiezas(15);
    $otra = marcaConPiezas(15);
    obraPagaProcesos($una->obra_id);
    obraPagaProcesos($otra->obra_id);

    $piezas = $una->piezas->merge($otra->piezas);

    [, $consultas] = contandoConsultas(
        fn () => app(AvanceDePiezas::class)->decorar($piezas, [$this->soldadura->id]),
    );

    // Dos obras: el trabajo se hace dos veces, no treinta.
    expect($consultas)->toBeLessThan(15);
});

test('preguntar el avance de la misma obra muchas veces no lo recalcula', function () {
    $marca = marcaConPiezas(10);
    obraPagaProcesos($marca->obra_id);

    $avance = app(AvanceDePiezas::class);

    [, $primera] = contandoConsultas(fn () => $avance->disponible($marca->piezas[0], $this->soldadura->id));

    [, $resto] = contandoConsultas(function () use ($avance, $marca) {
        foreach ($marca->piezas as $pieza) {
            $avance->disponible($pieza, $this->soldadura->id);
        }
    });

    expect($primera)->toBeGreaterThan(0)
        // La primera paga el cálculo; las diez siguientes leen la caché.
        ->and($resto)->toBe(0);
});

test('capturar muchos QS de golpe no escanea la obra una vez por QS', function () {
    $marca = marcaConPiezas(20);
    obraPagaProcesos($marca->obra_id);

    [$respuesta, $consultas] = contandoConsultas(fn () => $this->actingAs($this->user)
        ->post(route('admin.prod.destajos.registros.store', $this->destajo), [
            'fecha' => '2026-02-05',
            'piezas' => $marca->piezas->pluck('id')->all(),
            'proceso_id' => $this->soldadura->id,
            'grupo_trabajo_id' => $this->grupo->id,
        ]));

    $respuesta->assertSessionHasNoErrors();

    // 20 inserts + el resto del request. Antes eran además 20 escaneos completos
    // de la obra, uno por cada `cabe()`.
    expect($consultas)->toBeLessThan(60);
});

test('el mismo QS repetido en el payload se paga una sola vez', function () {
    $marca = marcaConPiezas(1);
    obraPagaProcesos($marca->obra_id);
    $pieza = $marca->piezas->first();

    $this->actingAs($this->user)
        ->post(route('admin.prod.destajos.registros.store', $this->destajo), [
            'fecha' => '2026-02-05',
            'piezas' => [$pieza->id, $pieza->id, $pieza->id],
            'proceso_id' => $this->soldadura->id,
            'grupo_trabajo_id' => $this->grupo->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Pieza::find($pieza->id)->registros()->count())->toBe(1)
        ->and(app(AvanceDePiezas::class)->disponible($pieza->fresh(), $this->soldadura->id))->toBe(0.0);
});
