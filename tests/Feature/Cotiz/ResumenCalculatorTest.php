<?php

use App\Services\Cotiz\ResumenCalculator;

beforeEach(function () {
    $this->calc = app(ResumenCalculator::class);
});

describe('coefEfectivo', function () {
    test('prioridad celda > fila > default', function () {
        expect($this->calc->coefEfectivo(1, 0.5, 10, [], []))->toBe(0.5);                 // default
        expect($this->calc->coefEfectivo(1, 0.5, 10, [1 => 0.8], []))->toBe(0.8);          // override fila
        expect($this->calc->coefEfectivo(1, 0.5, 10, [1 => 0.8], ['1-10' => 0.9]))->toBe(0.9); // override celda
        expect($this->calc->coefEfectivo(1, null, 10, [], []))->toBe(0.0);                 // sin default → 0
    });
});

describe('calcularMatriz — caso conocido', function () {
    test('materiales, mo_fab, subtotales, flete prorrateado, margen y total', function () {
        $filas = [
            ['id' => 1, 'bloque' => 'MO_FAB', 'tipo_formula' => 'materiales', 'coef_default' => null, 'referencia_extra' => null, 'orden' => 1],
            ['id' => 2, 'bloque' => 'MO_FAB', 'tipo_formula' => 'mo_fab_subgrupo', 'coef_default' => 0.1, 'referencia_extra' => null, 'orden' => 2],
            ['id' => 3, 'bloque' => 'MO_FAB', 'tipo_formula' => 'subtotal', 'coef_default' => null, 'referencia_extra' => null, 'orden' => 3],
            ['id' => 4, 'bloque' => 'MO_MONTAJE', 'tipo_formula' => 'por_kg', 'coef_default' => 2.0, 'referencia_extra' => null, 'orden' => 4],
            ['id' => 5, 'bloque' => 'MO_MONTAJE', 'tipo_formula' => 'subtotal', 'coef_default' => null, 'referencia_extra' => null, 'orden' => 5],
            ['id' => 6, 'bloque' => 'EXTRAS', 'tipo_formula' => 'flete_kg_prorrateado', 'coef_default' => 1.0, 'referencia_extra' => 'EXPL_MO_F54', 'orden' => 6],
            ['id' => 7, 'bloque' => 'TOTALES', 'tipo_formula' => 'margen', 'coef_default' => 0.1, 'referencia_extra' => null, 'orden' => 7],
            ['id' => 8, 'bloque' => 'TOTALES', 'tipo_formula' => 'total', 'coef_default' => null, 'referencia_extra' => null, 'orden' => 8],
        ];
        $columnas = [
            ['columna_id' => 10, 'kg' => 100.0, 'm2_pintura' => 0.0, 'm2_montaje' => 0.0, 'importe_materiales' => 1000.0, 'sueldo_mo_pza' => 5.0],
        ];
        $obra = ['kg_total' => 100.0, 'm2_montaje_total' => 0.0, 'extras' => ['EXPL_MO_F54' => 500.0]];
        $overrides = ['por_fila' => [], 'por_celda' => []];

        $m = $this->calc->calcularMatriz($filas, $columnas, $obra, $overrides);

        expect($m[1][10])->toEqualWithDelta(1000.0, 1e-9)  // materiales
            ->and($m[2][10])->toEqualWithDelta(50.0, 1e-9)   // 0.1 × 100 × 5
            ->and($m[3][10])->toEqualWithDelta(1050.0, 1e-9) // subtotal acumulado = 1000 + 50
            ->and($m[4][10])->toEqualWithDelta(200.0, 1e-9)  // 2 × 100
            ->and($m[5][10])->toEqualWithDelta(1250.0, 1e-9) // subtotal acumulado = 1050 + 200
            ->and($m[6][10])->toEqualWithDelta(500.0, 1e-9)  // (100/100) × 500 × 1
            ->and($m[7][10])->toEqualWithDelta(175.0, 1e-9)  // costo directo 1750 × 0.1
            ->and($m[8][10])->toEqualWithDelta(1925.0, 1e-9); // 1750 + 175
    });

    test('mo_fab_subgrupo con override de celda usa $/kg directo (sin × sueldo)', function () {
        $filas = [
            ['id' => 1, 'bloque' => 'MO_FAB', 'tipo_formula' => 'mo_fab_subgrupo', 'coef_default' => 0.1, 'referencia_extra' => null, 'orden' => 1],
        ];
        $columnas = [
            ['columna_id' => 10, 'kg' => 100.0, 'm2_pintura' => 0.0, 'm2_montaje' => 0.0, 'importe_materiales' => 0.0, 'sueldo_mo_pza' => 5.0],
        ];
        $obra = ['kg_total' => 100.0, 'm2_montaje_total' => 0.0, 'extras' => []];

        // Con override de celda (coef = 4) → importe = 4 × kg = 400 (no × sueldo).
        $m = $this->calc->calcularMatriz($filas, $columnas, $obra, ['por_fila' => [], 'por_celda' => ['1-10' => 4.0]]);
        expect($m[1][10])->toEqualWithDelta(400.0, 1e-9);
    });

    test('flete prorrateado reparte el extra por fracción de kg entre columnas', function () {
        $filas = [
            ['id' => 1, 'bloque' => 'EXTRAS', 'tipo_formula' => 'flete_kg_prorrateado', 'coef_default' => 1.0, 'referencia_extra' => 'EXPL_MO_F54', 'orden' => 1],
        ];
        $columnas = [
            ['columna_id' => 10, 'kg' => 75.0, 'm2_pintura' => 0.0, 'm2_montaje' => 0.0, 'importe_materiales' => 0.0, 'sueldo_mo_pza' => 0.0],
            ['columna_id' => 20, 'kg' => 25.0, 'm2_pintura' => 0.0, 'm2_montaje' => 0.0, 'importe_materiales' => 0.0, 'sueldo_mo_pza' => 0.0],
        ];
        $obra = ['kg_total' => 100.0, 'm2_montaje_total' => 0.0, 'extras' => ['EXPL_MO_F54' => 800.0]];

        $m = $this->calc->calcularMatriz($filas, $columnas, $obra, ['por_fila' => [], 'por_celda' => []]);
        expect($m[1][10])->toEqualWithDelta(600.0, 1e-9)   // 0.75 × 800
            ->and($m[1][20])->toEqualWithDelta(200.0, 1e-9); // 0.25 × 800
    });
});
