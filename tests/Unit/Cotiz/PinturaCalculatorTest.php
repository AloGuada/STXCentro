<?php

use App\Services\Cotiz\FormulaEvaluator;
use App\Services\Cotiz\PinturaCalculator;

beforeEach(function () {
    $this->pintura = new PinturaCalculator(new FormulaEvaluator);
});

describe('inferirTipo', function () {
    test('galvanizado nunca se pinta', function () {
        expect($this->pintura->inferirTipo('Placa galvanizada cal 20'))->toBe('no_pinta');
    });

    test('tornillería y consumibles no se pintan', function () {
        expect($this->pintura->inferirTipo('Tornillo A325'))->toBe('no_pinta');
        expect($this->pintura->inferirTipo('Pija punta broca'))->toBe('no_pinta');
        expect($this->pintura->inferirTipo('Lámina losacero'))->toBe('no_pinta');
    });

    test('HSS y APS → hss', function () {
        expect($this->pintura->inferirTipo('HSS de 6" x 1/4"'))->toBe('hss');
        expect($this->pintura->inferirTipo('APS 1/4" x 3"'))->toBe('hss');
    });

    test('IR/IPR/W → ipr', function () {
        expect($this->pintura->inferirTipo('IR 406 x 46.20Kg/m'))->toBe('ipr');
        expect($this->pintura->inferirTipo('W16x31'))->toBe('ipr');
    });

    test('placa → placa', function () {
        expect($this->pintura->inferirTipo('Placa A36 1/2"'))->toBe('placa');
    });

    test('descripción vacía → no_pinta', function () {
        expect($this->pintura->inferirTipo(null))->toBe('no_pinta');
        expect($this->pintura->inferirTipo(''))->toBe('no_pinta');
    });
});

describe('parseo de dimensiones', function () {
    test('extrae el lado en pulgadas de HSS', function () {
        expect($this->pintura->extraerLadoPulgadas('HSS de 6" x 1/4"'))->toBe(6.0);
    });

    test('extrae el peralte en metros de IR (mm)', function () {
        expect($this->pintura->extraerPeralteMetros('IR 406 x 46.20Kg/m'))->toEqualWithDelta(0.406, 0.0001);
    });

    test('extrae el peralte de W (pulgadas → m)', function () {
        expect($this->pintura->extraerPeralteMetros('W16x31'))->toEqualWithDelta(0.4064, 0.0001);
    });
});

describe('area', function () {
    test('placa: kg × 2 / peso_lineal (ambas caras)', function () {
        $area = $this->pintura->area('placa', 100.0, 50.0, 'Placa A36', ['placa' => 'kg * 2 / peso_lineal']);
        expect($area)->toEqualWithDelta(4.0, 0.0001);
    });

    test('sin kilos o sin peso lineal → 0', function () {
        expect($this->pintura->area('placa', 0.0, 50.0, 'Placa', ['placa' => 'kg * 2 / peso_lineal']))->toBe(0.0);
        expect($this->pintura->area('placa', 100.0, 0.0, 'Placa', ['placa' => 'kg * 2 / peso_lineal']))->toBe(0.0);
    });

    test('clave sin fórmula en el catálogo → 0', function () {
        expect($this->pintura->area('no_pinta', 100.0, 50.0, 'X', []))->toBe(0.0);
    });

    test("'auto' infiere la clave por la descripción", function () {
        $area = $this->pintura->area('auto', 100.0, 50.0, 'Placa A36', ['placa' => 'kg / peso_lineal']);
        expect($area)->toEqualWithDelta(2.0, 0.0001);
    });
});
