<?php

use App\Services\Cotiz\Variables\Parser;

describe('parse', function () {
    test('dirección self simple', function () {
        $dir = Parser::parse('total.tarjeta.kg');
        expect($dir)->not->toBeNull();
        expect($dir->op)->toBe('total');
        expect($dir->dominio)->toBe('tarjeta');
        expect($dir->columna)->toBe('kg');
        expect($dir->instancia)->toBeNull();
        expect($dir->filtro)->toBeNull();
    });

    test('dirección con filtro cc', function () {
        $dir = Parser::parse('total.tarjeta.importe[cc=acero]');
        expect($dir->filtro)->toBe(['clave' => 'cc', 'valor' => 'acero']);
    });

    test('factor[cod=X] es operación valor', function () {
        $dir = Parser::parse('tarjeta.factor[cod=OXIGENO_PLANTA]');
        expect($dir->op)->toBe('valor');
        expect($dir->columna)->toBe('factor');
        expect($dir->filtro)->toBe(['clave' => 'cod', 'valor' => 'OXIGENO_PLANTA']);
    });

    test('instancia entrecomillada con espacios y puntos', function () {
        $dir = Parser::parse('total.generadora#"EST. PPAL".kg');
        expect($dir->instancia)->toBe('EST. PPAL');
        expect($dir->dominio)->toBe('generadora');
    });

    test('kg_real con filtro corte', function () {
        $dir = Parser::parse('total.tarjeta.kg_real[corte=cnx]');
        expect($dir->columna)->toBe('kg_real');
        expect($dir->filtro)->toBe(['clave' => 'corte', 'valor' => 'cnx']);
    });

    test('un identificador plano (código de factor) no es dirección', function () {
        expect(Parser::parse('OXIGENO_PLANTA'))->toBeNull();
    });

    test('una función no es dirección', function () {
        expect(Parser::parse('ceil(x)'))->toBeNull();
    });

    test('dominio inexistente no parsea', function () {
        expect(Parser::parse('total.monstruo.kg'))->toBeNull();
    });
});

describe('extraer', function () {
    test('extrae todas las direcciones de una fórmula', function () {
        $ocs = Parser::extraer('total.tarjeta.kg * 0.5 + tarjeta.factor[cod=X]');
        expect($ocs)->toHaveCount(2);
        expect($ocs[0]['raw'])->toBe('total.tarjeta.kg');
        expect($ocs[1]['raw'])->toBe('tarjeta.factor[cod=X]');
    });

    test('no captura códigos de factor planos', function () {
        expect(Parser::extraer('OXIGENO * 2 + kg_fab'))->toHaveCount(0);
    });

    test('no captura una dirección precedida por punto o almohadilla', function () {
        // 'x.tarjeta.kg' no debe capturar 'tarjeta.kg' (precedida por punto).
        expect(Parser::extraer('x.tarjeta.kg'))->toHaveCount(0);
    });
});
