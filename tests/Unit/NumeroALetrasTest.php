<?php

use App\Support\NumeroALetras;

test('convierte montos a letras estilo facturación', function (float $monto, string $esperado) {
    expect(NumeroALetras::convertir($monto))->toBe($esperado);
})->with([
    [0, 'CERO PESOS 00/100 M.N.'],
    [1, 'UN PESOS 00/100 M.N.'],
    [100, 'CIEN PESOS 00/100 M.N.'],
    [101, 'CIENTO UN PESOS 00/100 M.N.'],
    [1234.50, 'MIL DOSCIENTOS TREINTA Y CUATRO PESOS 50/100 M.N.'],
    [2000000, 'DOS MILLONES PESOS 00/100 M.N.'],
    [45.99, 'CUARENTA Y CINCO PESOS 99/100 M.N.'],
]);

test('respeta la moneda', function () {
    expect(NumeroALetras::convertir(7500.00, 'usd'))->toBe('SIETE MIL QUINIENTOS DÓLARES 00/100');
});
