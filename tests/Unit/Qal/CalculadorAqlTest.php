<?php

use App\Enums\Qal\NivelAql;
use App\Enums\Qal\VeredictoLote;
use App\Services\Qal\CalculadorAql;

/**
 * La tabla AQL 10, tramo por tramo. Es norma de la empresa: si alguien la
 * «corrige» sin querer, aquí se nota.
 */
test('cada tramo da su muestra, su aceptacion y su rechazo', function (int $lote, NivelAql $nivel, array $esperado) {
    expect((new CalculadorAql)->plan($lote, $nivel))->toBe($esperado);
})->with([
    'hasta 8' => [8, NivelAql::Normal, ['muestra' => 2, 'aceptacion' => 1, 'rechazo' => 2]],
    'hasta 15' => [9, NivelAql::Normal, ['muestra' => 3, 'aceptacion' => 1, 'rechazo' => 2]],
    'hasta 25' => [25, NivelAql::Severa, ['muestra' => 8, 'aceptacion' => 1, 'rechazo' => 2]],
    'hasta 50' => [50, NivelAql::Normal, ['muestra' => 8, 'aceptacion' => 2, 'rechazo' => 3]],
    'hasta 90' => [51, NivelAql::Reducida, ['muestra' => 2, 'aceptacion' => 0, 'rechazo' => 1]],
    'hasta 150' => [100, NivelAql::Normal, ['muestra' => 20, 'aceptacion' => 5, 'rechazo' => 6]],
    'hasta 280' => [280, NivelAql::Severa, ['muestra' => 50, 'aceptacion' => 8, 'rechazo' => 9]],
    'hasta 500' => [500, NivelAql::Normal, ['muestra' => 50, 'aceptacion' => 10, 'rechazo' => 11]],
]);

test('por encima de 500 piezas rige el ultimo tramo y un lote vacio no tiene plan', function () {
    $aql = new CalculadorAql;

    expect($aql->plan(2000, NivelAql::Normal))->toBe(['muestra' => 50, 'aceptacion' => 10, 'rechazo' => 11])
        ->and($aql->plan(0, NivelAql::Normal))->toBeNull();
});

test('la entrega recorta la muestra: no se miran mas unidades de las que llegaron', function () {
    expect((new CalculadorAql)->plan(100, NivelAql::Normal, tope: 7)['muestra'])->toBe(7);
});

/**
 * Mientras la muestra no se completa no hay veredicto, salvo que las
 * rechazadas ya alcancen el rechazo: eso cierra el lote aunque falten piezas.
 */
test('el veredicto espera la muestra completa salvo que el rechazo ya se alcanzo', function () {
    $aql = new CalculadorAql;
    $plan = $aql->plan(100, NivelAql::Normal);

    expect($aql->veredicto($plan, 10, 2))->toBeNull()
        ->and($aql->veredicto($plan, 0, 6))->toBe(VeredictoLote::Rechazado)
        ->and($aql->veredicto($plan, 15, 5))->toBe(VeredictoLote::Aceptado);
});
