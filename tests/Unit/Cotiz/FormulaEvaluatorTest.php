<?php

use App\Services\Cotiz\FormulaEvaluator;

beforeEach(function () {
    $this->evaluator = new FormulaEvaluator;
});

describe('evaluar', function () {
    it('resuelve aritmética básica con variables', function () {
        expect($this->evaluator->evaluar('kg * 0.000428 * 1.25 * 0.8', ['kg' => 1000]))
            ->toEqualWithDelta(0.428, 0.0000001);
    });

    it('expone las variables del contexto', function () {
        expect($this->evaluator->evaluar('ancho * largo * cantidad', [
            'ancho' => 2.0,
            'largo' => 3.0,
            'cantidad' => 4,
        ]))->toBe(24.0);
    });

    it('usa ** para la potencia (no ^)', function () {
        expect($this->evaluator->evaluar('2 ** 3'))->toBe(8.0);
    });

    it('soporta el módulo con %', function () {
        expect($this->evaluator->evaluar('10 % 3'))->toBe(1.0);
    });

    it('devuelve null cuando una variable no está definida', function () {
        expect($this->evaluator->evaluar('kg_fab * 2', []))->toBeNull();
    });

    it('devuelve null ante división por cero', function () {
        expect($this->evaluator->evaluar('kg / cero', ['kg' => 10, 'cero' => 0]))->toBeNull();
    });

    it('devuelve null ante fórmula vacía', function () {
        expect($this->evaluator->evaluar('   '))->toBeNull();
    });

    it('devuelve null ante sintaxis inválida', function () {
        expect($this->evaluator->evaluar('kg * * 2', ['kg' => 1]))->toBeNull();
    });
});

describe('roundup', function () {
    it('redondea hacia arriba alejándose de cero', function () {
        expect($this->evaluator->evaluar('roundup(7.21)'))->toBe(8.0)
            ->and($this->evaluator->evaluar('roundup(1.91, 1)'))->toBe(2.0)
            ->and($this->evaluator->evaluar('roundup(0.034, 2)'))->toBe(0.04);
    });

    it('respeta el signo', function () {
        expect($this->evaluator->evaluar('roundup(-1.1)'))->toBe(-2.0)
            ->and($this->evaluator->evaluar('roundup(0)'))->toBe(0.0);
    });
});

describe('funciones matemáticas registradas', function () {
    it('expone ceil, floor, abs y round', function () {
        expect($this->evaluator->evaluar('ceil(1.2)'))->toBe(2.0)
            ->and($this->evaluator->evaluar('floor(1.8)'))->toBe(1.0)
            ->and($this->evaluator->evaluar('abs(-5)'))->toBe(5.0)
            ->and($this->evaluator->evaluar('round(2.555, 2)'))->toBe(2.56);
    });
});

describe('validar', function () {
    it('devuelve null para fórmula válida con variables desconocidas', function () {
        expect($this->evaluator->validar('total_kg * 0.5 + roundup(area, 2)'))->toBeNull();
    });

    it('devuelve null para fórmula vacía', function () {
        expect($this->evaluator->validar(''))->toBeNull();
    });

    it('devuelve mensaje de error para sintaxis inválida', function () {
        expect($this->evaluator->validar('kg * * 2'))->toBeString();
    });
});
