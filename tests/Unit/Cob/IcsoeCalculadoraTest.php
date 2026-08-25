<?php

use App\Exceptions\Cob\CatalogoSbcVacioException;
use App\Services\Cob\IcsoeCalculadora;
use App\Services\Cob\SbcResolver;
use Illuminate\Support\Carbon;

/** Catálogo de referencia: los mismos años del bosquejo del usuario. */
function resolverSbc(array $filas = []): SbcResolver
{
    return new SbcResolver($filas ?: [
        ['anio' => 2023, 'sbc' => 217.67, 'costo_m2' => 1154, 'prima_riesgo' => 7.58875],
        ['anio' => 2024, 'sbc' => 248.93, 'costo_m2' => 1154, 'prima_riesgo' => 7.58875],
        ['anio' => 2025, 'sbc' => 275.00, 'costo_m2' => 1154, 'prima_riesgo' => 7.58875],
        ['anio' => 2026, 'sbc' => 290.00, 'costo_m2' => 1154, 'prima_riesgo' => 7.58875],
    ]);
}

beforeEach(function () {
    $this->calc = new IcsoeCalculadora;
});

describe('desglose mensual', function () {
    it('reproduce el caso del bosquejo: 89 días en cuatro meses', function () {
        $r = $this->calc->desglose(Carbon::parse('2023-02-06'), Carbon::parse('2023-05-05'), resolverSbc());

        expect($r['total_dias'])->toBe(89);
        expect($r['meses'])->toHaveCount(4);
        expect(array_column($r['meses'], 'dias_proyecto'))->toBe([23, 31, 30, 5]);
        expect(array_column($r['meses'], 'mes'))->toBe([2, 3, 4, 5]);
        expect(array_column($r['meses'], 'anio'))->toBe([2023, 2023, 2023, 2023]);
    });

    it('cuenta un solo día cuando inicio y fin son el mismo', function () {
        $r = $this->calc->desglose(Carbon::parse('2023-03-15'), Carbon::parse('2023-03-15'), resolverSbc());

        expect($r['total_dias'])->toBe(1);
        expect($r['meses'])->toHaveCount(1);
        expect($r['meses'][0]['dias_proyecto'])->toBe(1);
    });

    it('cuenta el mes natural completo', function () {
        $r = $this->calc->desglose(Carbon::parse('2023-03-01'), Carbon::parse('2023-03-31'), resolverSbc());

        expect($r['total_dias'])->toBe(31);
        expect($r['meses'])->toHaveCount(1);
    });

    it('respeta el año bisiesto', function () {
        $r = $this->calc->desglose(Carbon::parse('2024-02-01'), Carbon::parse('2024-02-29'), resolverSbc());

        expect($r['total_dias'])->toBe(29);
    });

    it('cruza el año aplicando el SBC de cada año', function () {
        $r = $this->calc->desglose(Carbon::parse('2023-12-15'), Carbon::parse('2024-01-10'), resolverSbc());

        expect($r['total_dias'])->toBe(27);
        expect(array_column($r['meses'], 'dias_proyecto'))->toBe([17, 10]);
        expect($r['meses'][0]['sbc'])->toBe(217.67);
        expect($r['meses'][1]['sbc'])->toBe(248.93);
    });

    it('no se salta febrero al avanzar desde un 31', function () {
        $r = $this->calc->desglose(Carbon::parse('2023-01-31'), Carbon::parse('2023-02-01'), resolverSbc());

        expect($r['total_dias'])->toBe(2);
        expect(array_column($r['meses'], 'mes'))->toBe([1, 2]);
        expect(array_column($r['meses'], 'dias_proyecto'))->toBe([1, 1]);
    });

    it('devuelve vacío si el fin es anterior al inicio', function () {
        $r = $this->calc->desglose(Carbon::parse('2023-05-05'), Carbon::parse('2023-02-06'), resolverSbc());

        expect($r['total_dias'])->toBe(0);
        expect($r['meses'])->toBe([]);
    });

    it('ignora la hora del día en las fechas', function () {
        $r = $this->calc->desglose(
            Carbon::parse('2023-02-06 23:59:00'),
            Carbon::parse('2023-05-05 00:00:01'),
            resolverSbc(),
        );

        expect($r['total_dias'])->toBe(89);
    });
});

describe('resolución del SBC por año', function () {
    it('toma el año exacto cuando existe', function () {
        expect(resolverSbc()->sbc(2025))->toBe(275.00);
    });

    it('hereda el último año capturado para años futuros', function () {
        expect(resolverSbc()->sbc(2030))->toBe(290.00);
    });

    it('usa el año mínimo para años anteriores al catálogo', function () {
        expect(resolverSbc()->sbc(2019))->toBe(217.67);
    });

    it('resuelve costo por m² y prima con la misma regla', function () {
        $r = resolverSbc([
            ['anio' => 2024, 'sbc' => 248.93, 'costo_m2' => 1154, 'prima_riesgo' => 7.58875],
            ['anio' => 2026, 'sbc' => 290.00, 'costo_m2' => 1300, 'prima_riesgo' => 5.5],
        ]);

        expect($r->costoM2(2025))->toBe(1154.0);
        expect($r->costoM2(2027))->toBe(1300.0);
        expect($r->primaRiesgo(2026))->toBe(5.5);
    });

    it('truena si el catálogo está vacío', function () {
        expect(fn () => (new SbcResolver([]))->sbc(2026))
            ->toThrow(CatalogoSbcVacioException::class);
    });
});
