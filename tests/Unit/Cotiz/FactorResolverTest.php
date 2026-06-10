<?php

use App\Services\Cotiz\FactorResolver;
use App\Services\Cotiz\FormulaEvaluator;

beforeEach(function () {
    $this->resolver = new FactorResolver(new FormulaEvaluator);
});

it('resuelve factores que solo dependen de variables base', function () {
    $valores = $this->resolver->resolver(
        factores: [
            ['codigo' => 'OXIGENO', 'formula' => 'kg_fab * 0.000428 * 0.8'],
        ],
        variablesBase: ['kg_fab' => 1000, 'area_pintura' => 0],
    );

    expect($valores['OXIGENO'])->toEqualWithDelta(0.3424, 0.0001);
});

it('resuelve un DAG donde un factor depende de otro factor', function () {
    $valores = $this->resolver->resolver(
        factores: [
            ['codigo' => 'BUTANO', 'formula' => 'OXIGENO * 0.2'],
            ['codigo' => 'OXIGENO', 'formula' => 'kg_fab * 0.001'],
        ],
        variablesBase: ['kg_fab' => 1000],
    );

    expect($valores['OXIGENO'])->toEqualWithDelta(1.0, 0.0001)
        ->and($valores['BUTANO'])->toEqualWithDelta(0.2, 0.0001);
});

it('toma el valor de cantidades manuales cuando la fórmula es null', function () {
    $valores = $this->resolver->resolver(
        factores: [
            ['codigo' => 'MANUAL', 'formula' => null],
        ],
        variablesBase: [],
        cantidadesManuales: ['MANUAL' => 42.5],
    );

    expect($valores['MANUAL'])->toBe(42.5);
});

it('usa 0 para un factor manual sin cantidad declarada', function () {
    $valores = $this->resolver->resolver(
        factores: [['codigo' => 'MANUAL', 'formula' => null]],
        variablesBase: [],
    );

    expect($valores['MANUAL'])->toBe(0.0);
});

it('cae a 0 cuando un factor referencia una variable inexistente', function () {
    $valores = $this->resolver->resolver(
        factores: [['codigo' => 'ROTO', 'formula' => 'no_existe * 2']],
        variablesBase: ['kg_fab' => 10],
    );

    expect($valores['ROTO'])->toBe(0.0);
});

it('cae a 0 ante una dependencia cíclica sin colgarse', function () {
    $valores = $this->resolver->resolver(
        factores: [
            ['codigo' => 'A', 'formula' => 'B + 1'],
            ['codigo' => 'B', 'formula' => 'A + 1'],
        ],
        variablesBase: [],
    );

    expect($valores['A'])->toBe(0.0)
        ->and($valores['B'])->toBe(0.0);
});

it('resuelve cadenas largas dentro del límite de pasadas', function () {
    $valores = $this->resolver->resolver(
        factores: [
            ['codigo' => 'E', 'formula' => 'D + 1'],
            ['codigo' => 'D', 'formula' => 'C + 1'],
            ['codigo' => 'C', 'formula' => 'B + 1'],
            ['codigo' => 'B', 'formula' => 'A + 1'],
            ['codigo' => 'A', 'formula' => 'base'],
        ],
        variablesBase: ['base' => 0],
    );

    expect($valores['E'])->toBe(4.0);
});

it('aplica el expansor de direcciones semánticas cuando se provee', function () {
    $expandir = function (string $formula, array $valores): array {
        return [
            'formula' => str_replace('total.tarjeta.kg', '__v0', $formula),
            'vars' => ['__v0' => 500.0],
        ];
    };

    $valores = $this->resolver->resolver(
        factores: [['codigo' => 'PLASMA', 'formula' => 'total.tarjeta.kg * 0.02']],
        variablesBase: [],
        cantidadesManuales: [],
        expandir: $expandir,
    );

    expect($valores['PLASMA'])->toEqualWithDelta(10.0, 0.0001);
});

it('deja sin resolver (0) cuando el expansor lanza', function () {
    $expandir = function (string $formula, array $valores): array {
        throw new RuntimeException('instancia inexistente');
    };

    $valores = $this->resolver->resolver(
        factores: [['codigo' => 'PLASMA', 'formula' => 'total.tarjeta.kg * 0.02']],
        variablesBase: [],
        cantidadesManuales: [],
        expandir: $expandir,
    );

    expect($valores['PLASMA'])->toBe(0.0);
});
