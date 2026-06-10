<?php

use App\Models\Cotiz\Generadora;
use App\Models\Cotiz\GeneradoraRegistro;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\Merma;
use App\Models\Usuario;
use App\Services\Cotiz\FormulaEvaluator;
use App\Services\Cotiz\MermaCalculator;

function registroCon(array $attrs = [], ?float $pesoLineal = 10.0): GeneradoraRegistro
{
    $insumo = $pesoLineal === null
        ? null
        : Insumo::factory()->create(['peso_lineal' => $pesoLineal]);

    return GeneradoraRegistro::factory()->create(array_merge([
        'material_origen_id' => $insumo?->id,
        'ancho' => 2,
        'largo' => 3,
        'cantidad' => 4,
        'cant_pzas' => 1,
    ], $attrs))->fresh(['materialOrigen', 'merma']);
}

describe('accessors derivados', function () {
    it('t_ml_m2 = ancho × largo × cantidad × cant_pzas', function () {
        $r = registroCon(['ancho' => 2, 'largo' => 3, 'cantidad' => 4, 'cant_pzas' => 2]);

        expect($r->t_ml_m2)->toBe(48.0);
    });

    it('t_ml_m2 es null si falta un factor', function () {
        $r = registroCon(['cant_pzas' => null]);

        expect($r->t_ml_m2)->toBeNull();
    });

    it('kilos_reales = t_ml_m2 × peso_lineal del insumo', function () {
        $r = registroCon(['ancho' => 2, 'largo' => 3, 'cantidad' => 4, 'cant_pzas' => 1], pesoLineal: 10);

        expect($r->kilos_reales)->toBe(240.0);
    });

    it('kilos_reales es null si el registro no tiene insumo de origen', function () {
        $r = registroCon([], pesoLineal: null);

        expect($r->kilos_reales)->toBeNull();
    });
});

describe('MermaCalculator', function () {
    it('devuelve kilos_reales cuando la merma no tiene fórmula', function () {
        $merma = Merma::factory()->create(['formula' => '']);
        $r = registroCon(['merma_id' => $merma->id]);

        expect(app(MermaCalculator::class)->aplicar($r))->toBe(240.0);
    });

    it('aplica la fórmula de merma sobre los kilos reales', function () {
        $merma = Merma::factory()->create(['formula' => 'kilos_reales * 1.03']);
        $r = registroCon(['merma_id' => $merma->id]);

        expect(app(MermaCalculator::class)->aplicar($r))->toEqualWithDelta(247.2, 0.001);
    });

    it('sustituye variables nulas por 0', function () {
        $calc = new MermaCalculator(new FormulaEvaluator);

        expect($calc->evaluar('t_ml_m2 + kilos_reales', ['t_ml_m2' => null, 'kilos_reales' => 5]))->toBe(5.0);
    });

    it('cae a 0 ante división por cero', function () {
        $calc = new MermaCalculator(new FormulaEvaluator);

        expect($calc->evaluar('kg / cero', ['kg' => 10, 'cero' => 0]))->toBe(0.0);
    });
});

describe('lock estricto de generadora', function () {
    it('un segundo usuario no puede tomar un lock vivo de otro', function () {
        $a = Usuario::factory()->create();
        $b = Usuario::factory()->create();
        $generadora = Generadora::factory()->create();

        expect($generadora->lock($a->id))->toBeTrue()
            ->and($generadora->fresh()->lock($b->id))->toBeFalse();
    });

    it('el titular puede re-lockear (heartbeat) y liberar', function () {
        $a = Usuario::factory()->create();
        $generadora = Generadora::factory()->create();

        expect($generadora->lock($a->id))->toBeTrue()
            ->and($generadora->fresh()->lock($a->id))->toBeTrue();

        $generadora->fresh()->unlock($a->id);

        expect($generadora->fresh()->isLocked())->toBeFalse();
    });

    it('una liberación forzada (sin usuario) libera un lock ajeno', function () {
        $a = Usuario::factory()->create();
        $generadora = Generadora::factory()->create();
        $generadora->lock($a->id);

        $generadora->fresh()->unlock();

        expect($generadora->fresh()->isLocked())->toBeFalse();
    });
});
