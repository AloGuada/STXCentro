<?php

use App\Services\Cotiz\FormulaEvaluator;
use App\Services\Cotiz\Variables\Parser;
use App\Services\Cotiz\Variables\Validar;

beforeEach(function () {
    $this->evaluator = new FormulaEvaluator;
});

describe('direccion', function () {
    test('una dirección self válida pasa', function () {
        expect(Validar::direccion(Parser::parse('total.tarjeta.kg')))->toBeNull();
        expect(Validar::direccion(Parser::parse('total.tarjeta.kg_real[corte=tiras]')))->toBeNull();
        expect(Validar::direccion(Parser::parse('tarjeta.factor[cod=OXI]')))->toBeNull();
    });

    test('columna inexistente falla', function () {
        expect(Validar::direccion(Parser::parse('total.tarjeta.foo')))
            ->toContain('columna desconocida');
    });

    test('valor de filtro fuera del enum falla', function () {
        expect(Validar::direccion(Parser::parse('total.tarjeta.kg_real[corte=zzz]')))
            ->toContain('no válido');
    });

    test('operación incorrecta falla', function () {
        // kg solo admite total, no valor.
        expect(Validar::direccion(Parser::parse('tarjeta.kg')))
            ->toContain('admite');
    });

    test('dominio aún no implementado (seccion) falla', function () {
        expect(Validar::direccion(Parser::parse('total.seccion.importe')))
            ->toContain('aún no está implementado');
    });
});

describe('formula', function () {
    test('fórmula con direcciones válidas + aritmética pasa', function () {
        expect(Validar::formula('total.tarjeta.kg * 0.000428 * 0.8', $this->evaluator))->toBeNull();
        expect(Validar::formula('tarjeta.factor[cod=OXI] * 0.2', $this->evaluator))->toBeNull();
    });

    test('fórmula con dirección inválida devuelve su error', function () {
        expect(Validar::formula('total.tarjeta.foo * 2', $this->evaluator))
            ->toContain('columna desconocida');
    });

    test('fórmula vacía es válida', function () {
        expect(Validar::formula('', $this->evaluator))->toBeNull();
    });
});
