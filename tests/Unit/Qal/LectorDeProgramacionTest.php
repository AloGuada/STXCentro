<?php

use App\Services\Qal\LectorDeProgramacion;

/**
 * La lista que pega producción para programar la semana. Si el parseo se
 * equivoca, la semana entera se mide contra un plan que nadie escribió.
 */
test('una marca por linea es una pieza, y el numero final es parte de la marca', function () {
    expect((new LectorDeProgramacion)->marcas("PIP-CM1-1\nPIP-CM1-2\r\n\npip-tp2-7."))->toBe([
        ['marca' => 'PIP-CM1-1', 'cantidad' => 1, 'repetida' => false],
        ['marca' => 'PIP-CM1-2', 'cantidad' => 1, 'repetida' => false],
        ['marca' => 'PIP-TP2-7', 'cantidad' => 1, 'repetida' => false],
    ]);
});

test('la cantidad va con x o en una segunda columna de excel', function () {
    expect((new LectorDeProgramacion)->marcas("PIP-CM1-5 x3\nPIP-TP2-1\t4\nPIP-TS1-2X2"))->toBe([
        ['marca' => 'PIP-CM1-5', 'cantidad' => 3, 'repetida' => false],
        ['marca' => 'PIP-TP2-1', 'cantidad' => 4, 'repetida' => false],
        ['marca' => 'PIP-TS1-2', 'cantidad' => 2, 'repetida' => false],
    ]);
});

test('varias marcas en una linea se separan por coma o punto y coma', function () {
    $marcas = (new LectorDeProgramacion)->marcas('PIP-CM1-1, PIP-CM1-2; PIP-CM1-3');

    expect(array_column($marcas, 'marca'))->toBe(['PIP-CM1-1', 'PIP-CM1-2', 'PIP-CM1-3']);
});

test('una marca repetida se suma y se avisa', function () {
    expect((new LectorDeProgramacion)->marcas("PIP-CM1-1\npip-cm1-1 x2"))->toBe([
        ['marca' => 'PIP-CM1-1', 'cantidad' => 3, 'repetida' => true],
    ]);
});

test('las bajas leen su motivo despues de los dos puntos', function () {
    expect((new LectorDeProgramacion)->bajas("PIP-CM1-1: cambio de ingeniería\npip-tp2-7\nPIP-CM1-1: otra vez"))->toBe([
        ['marca' => 'PIP-CM1-1', 'motivo' => 'cambio de ingeniería'],
        ['marca' => 'PIP-TP2-7', 'motivo' => null],
    ]);
});

test('el tipo son las letras del segundo tramo de la marca', function (string $marca, string $tipo) {
    expect((new LectorDeProgramacion)->tipoDeMarca($marca))->toBe($tipo);
})->with([
    ['PIP-TP2-1', 'TP'],
    ['PIP-CVC1-3', 'CVC'],
    ['SINGUION', ''],
    ['PIP-12-1', ''],
]);
