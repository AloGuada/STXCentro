<?php

use App\Support\Moneda;

it('devuelve la divisa única cuando todas coinciden', function () {
    expect(Moneda::agregada(['usd', 'usd', 'usd']))->toBe('usd');
    expect(Moneda::agregada(['eur', 'EUR']))->toBe('eur');
});

it('cae a mxn cuando se mezclan monedas', function () {
    expect(Moneda::agregada(['usd', 'mxn']))->toBe('mxn');
    expect(Moneda::agregada(['usd', 'eur']))->toBe('mxn');
});

it('cae a mxn cuando no hay ninguna', function () {
    expect(Moneda::agregada([]))->toBe('mxn');
    expect(Moneda::agregada([null, null]))->toBe('mxn');
});

it('ignora nulos al determinar la única', function () {
    expect(Moneda::agregada(['usd', null, 'usd']))->toBe('usd');
});
