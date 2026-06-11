<?php

use App\Services\Cotiz\KilosRealesCalculator;

beforeEach(function () {
    $this->calc = new KilosRealesCalculator;
});

test('filas fijas suman directo a su tipo de corte', function () {
    $r = $this->calc->porTipoCorte(
        categorias: [
            ['categoria_id' => 1, 'tipo_corte' => 'TIRAS', 'porcentual' => null],
            ['categoria_id' => 2, 'tipo_corte' => 'KG', 'porcentual' => null],
        ],
        celdas: [
            ['categoria_id' => 1, 'kilos' => 100],
            ['categoria_id' => 1, 'kilos' => 50],
            ['categoria_id' => 2, 'kilos' => 30],
        ],
    );

    expect($r['TIRAS'])->toBe(150.0);
    expect($r['KG'])->toBe(30.0);
    expect($this->calc->total($r))->toBe(180.0);
});

test('filas porcentuales aportan % × Σ fijas a su tipo de corte', function () {
    $r = $this->calc->porTipoCorte(
        categorias: [
            ['categoria_id' => 1, 'tipo_corte' => 'TIRAS', 'porcentual' => null],
            ['categoria_id' => 2, 'tipo_corte' => 'CNX', 'porcentual' => 0.2],
        ],
        celdas: [
            ['categoria_id' => 1, 'kilos' => 100],
        ],
    );

    // Fijas: TIRAS=100 (sumFijas=100). Porcentual CNX: 0.2×100=20.
    expect($r['TIRAS'])->toBe(100.0);
    expect($r['CNX'])->toEqualWithDelta(20.0, 0.0001);
    expect($this->calc->total($r))->toEqualWithDelta(120.0, 0.0001);
});

test('sin celdas todo es cero', function () {
    $r = $this->calc->porTipoCorte([], []);
    expect($this->calc->total($r))->toBe(0.0);
});
