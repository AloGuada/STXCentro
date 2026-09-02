<?php

use App\Support\Code39;

describe('lo que Code 39 sabe representar', function () {
    it('acepta digitos, mayusculas y los signos de su tabla', function (string $valor) {
        expect(Code39::esCodificable($valor))->toBeTrue();
    })->with(['SAL-2608-0007', 'ART-00013', '12345', 'A B.C', 'REC-260803']);

    it('rechaza lo que ningun lector podria leer', function (string $valor) {
        expect(Code39::esCodificable($valor))->toBeFalse();
    })->with([
        'vacio' => '',
        'solo espacios' => '   ',
        'guion bajo' => 'SAL_260803',
        'acento' => 'SALIDA-Ñ',
        // El asterisco es el delimitador: dentro del dato cerraria el codigo
        // a media lectura.
        'asterisco' => 'SAL*01',
    ]);

    it('normaliza a mayusculas en vez de rechazar', function () {
        expect(Code39::esCodificable('sal-260803'))->toBeTrue()
            ->and(Code39::dibujo('sal-260803'))->toBe(Code39::dibujo('SAL-260803'));
    });
});

describe('el dibujo', function () {
    it('no devuelve nada cuando el valor no es imprimible', function () {
        expect(Code39::dibujo('SAL_260803'))->toBeNull();
    });

    /**
     * Cada caracter aporta 5 barras negras (los 9 elementos alternan empezando
     * por barra), y el codigo lleva dos delimitadores mas.
     */
    it('dibuja cinco barras por caracter, contando los delimitadores', function () {
        $dibujo = Code39::dibujo('SAL-260803');

        expect($dibujo['barras'])->toHaveCount((10 + 2) * 5);
    });

    /**
     * Cada caracter mide 15 modulos (6 angostos y 3 anchos) y entre caracter y
     * caracter va un modulo de espacio, salvo despues del ultimo.
     */
    it('mide quince modulos por caracter mas la separacion', function () {
        $caracteres = 10 + 2;

        expect(Code39::dibujo('SAL-260803')['modulos'])->toBe($caracteres * 15 + ($caracteres - 1));
    });

    it('empieza y termina en barra, sin barras encimadas', function () {
        $dibujo = Code39::dibujo('SAL-2608-0007');
        $barras = $dibujo['barras'];

        expect($barras[0]['x'])->toBe(0);

        $ultima = end($barras);
        expect($ultima['x'] + $ultima['ancho'])->toBe($dibujo['modulos']);

        foreach ($barras as $i => $barra) {
            expect($barra['ancho'])->toBeIn([1, 3]);

            if ($i > 0) {
                $previa = $barras[$i - 1];
                // Entre dos barras siempre hay papel: si una empezara donde
                // termina la anterior, el lector veria una sola barra ancha.
                expect($barra['x'])->toBeGreaterThan($previa['x'] + $previa['ancho']);
            }
        }
    });
});
