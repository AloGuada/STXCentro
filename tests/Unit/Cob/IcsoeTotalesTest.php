<?php

use App\Enums\Cob\IcsoeMetodo;
use App\Services\Cob\IcsoeCalculadora;

beforeEach(function () {
    $this->calc = new IcsoeCalculadora;
});

describe('meta de mano de obra', function () {
    it('multiplica superficie por costo del DOF', function () {
        $total = $this->calc->moEstimadaTotal(IcsoeMetodo::Superficie, 0, 30, 1164, 1154);

        expect($total)->toBe(1343256.0);
    });

    it('aplica el porcentaje al valor a ejecutar', function () {
        $total = $this->calc->moEstimadaTotal(IcsoeMetodo::Porcentaje, 15870818, 30, null, null);

        expect($total)->toEqualWithDelta(4761245.40, 0.01);
    });

    it('ignora la superficie cuando el método es porcentaje', function () {
        $total = $this->calc->moEstimadaTotal(IcsoeMetodo::Porcentaje, 1000000, 25, 1164, 1154);

        expect($total)->toBe(250000.0);
    });

    it('reparte la meta entre los días del periodo', function () {
        $diaria = $this->calc->moEstimadaDiaria(1343256.0, 89);

        expect($diaria)->toEqualWithDelta(15092.7640, 0.0001);
    });

    it('no divide entre cero cuando no hay días', function () {
        expect($this->calc->moEstimadaDiaria(1343256.0, 0))->toBe(0.0);
    });

    it('calcula la meta mensual de febrero del bosquejo', function () {
        $diaria = $this->calc->moEstimadaDiaria(1343256.0, 89);

        expect(23 * $diaria)->toEqualWithDelta(347133.57, 0.01);
    });
});

describe('mano de obra real y riesgo', function () {
    it('multiplica días cotizados por el SBC aplicado', function () {
        expect($this->calc->moReal(30, 217.67))->toEqualWithDelta(6530.10, 0.01);
    });

    it('cobra el riesgo sobre la diferencia con el factor de cargas sociales', function () {
        $diferencia = $this->calc->diferenciaMo(1343256.0, 343256.0);

        expect($diferencia)->toBe(1000000.0);
        // (26 + 7.58875) / 100 = 0.3358875
        expect($this->calc->montoRiesgo($diferencia, 7.58875))->toEqualWithDelta(335887.50, 0.01);
    });

    it('no cobra riesgo si se cotizó de más', function () {
        $diferencia = $this->calc->diferenciaMo(100000.0, 150000.0);

        expect($diferencia)->toBe(-50000.0);
        expect($this->calc->montoRiesgo($diferencia, 7.58875))->toBe(0.0);
    });

    it('mide la diferencia contra el total exacto, no contra la suma mensual', function () {
        // 1,000,000 en 3 días: cada mes redondeado a 2 decimales suma 999,999.99,
        // pero la diferencia debe calcularse contra el total exacto.
        $total = 1000000.0;
        $diaria = $this->calc->moEstimadaDiaria($total, 3);
        $sumaMensual = round($diaria, 2) * 3;

        expect($sumaMensual)->not->toBe($total);
        expect($this->calc->diferenciaMo($total, 0.0))->toBe($total);
    });
});
