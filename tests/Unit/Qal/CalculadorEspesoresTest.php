<?php

use App\Services\Qal\CalculadorEspesores;

/**
 * SSPC-PA2: el espesor de la pieza es el promedio de los promedios de cada
 * medición, y una medición baja avisa sin rechazar.
 */
test('promedia los promedios de las mediciones a la vista y anota las bajas', function () {
    $resumen = (new CalculadorEspesores)->resumir([
        1 => [2, 2, 2],
        2 => [4, 4, 4],
        3 => [3, 4, 5],
        4 => [5, null, null],
        5 => [null, null, null],
        // Oculta: el inspector quitó la columna, así que no se promedia.
        6 => [10, 10, 10],
    ], visibles: 5, requerido: 3.5);

    // (2 + 4 + 4 + 5) / 4: la medición 1 queda bajo el 80 % (2.8) y se
    // anota, pero el promedio sigue cumpliendo.
    expect($resumen)->toBe(['promedio' => 3.75, 'cumple' => true, 'bajas' => [1]]);
});

test('sin requerido no se afirma nada y sin lecturas no hay promedio', function () {
    $espesores = new CalculadorEspesores;

    expect($espesores->resumir([1 => [4, 4, 4]], 5, null)['cumple'])->toBeNull()
        ->and($espesores->resumir([1 => [null, null, null]], 5, 3.0))
        ->toBe(['promedio' => null, 'cumple' => null, 'bajas' => []]);
});
