<?php

use App\Services\Qal\Estadistica;

/**
 * Las funciones de estadística del tablero, contra valores de tabla: si el p del
 * chi-cuadrado o la normal se desvían, todas las lecturas de «sí influye» y
 * «mejorando» quedan mal sin que nada truene.
 */
test('el p del chi-cuadrado coincide con las tablas', function () {
    expect(Estadistica::pChiCuadrado(3.841, 1))->toEqualWithDelta(0.05, 0.001)
        ->and(Estadistica::pChiCuadrado(5.991, 2))->toEqualWithDelta(0.05, 0.001)
        ->and(Estadistica::pChiCuadrado(11.345, 3))->toEqualWithDelta(0.01, 0.001)
        ->and(Estadistica::pChiCuadrado(30.0, 4))->toBeLessThan(0.0001)
        ->and(Estadistica::pChiCuadrado(0.0, 3))->toEqual(1.0);
});

test('la normal acumulada coincide con las tablas', function () {
    expect(Estadistica::normalAcumulada(0))->toEqualWithDelta(0.5, 0.001)
        ->and(Estadistica::normalAcumulada(1.96))->toEqualWithDelta(0.975, 0.001)
        ->and(Estadistica::normalAcumulada(-1.96))->toEqualWithDelta(0.025, 0.001);
});

test('la binomial exacta de dos colas', function () {
    expect(Estadistica::pBinomialDosColas(4, 5, 0.4))->toEqualWithDelta(0.17408, 0.0001)
        ->and(Estadistica::pBinomialDosColas(0, 5, 0.4))->toEqualWithDelta(0.15552, 0.0001)
        ->and(Estadistica::pBinomialDosColas(5, 10, 0.5))->toEqual(1.0);
});

test('la descriptiva usa la sigma muestral y la mediana de en medio', function () {
    $descripcion = Estadistica::describir([9, 2, 4, 4, 5, 4, 7, 5]);

    expect($descripcion['n'])->toBe(8)
        ->and($descripcion['media'])->toEqual(5.0)
        ->and($descripcion['mediana'])->toEqual(4.5)
        ->and($descripcion['sigma'])->toEqualWithDelta(2.138, 0.001)
        ->and($descripcion['cv'])->toEqualWithDelta(42.76, 0.01)
        ->and([$descripcion['min'], $descripcion['max']])->toEqual([2.0, 9.0])
        ->and(Estadistica::describir([]))->toBeNull();
});
