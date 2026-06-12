<?php

use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraCuadrillaGlobal;
use App\Models\Cotiz\ObraFleteViatico;
use App\Models\Cotiz\PersonalCategoria;
use App\Services\Cotiz\FletesViaticosCalculator;

beforeEach(function () {
    $this->calc = app(FletesViaticosCalculator::class);
});

describe('evaluarItems (DAG)', function () {
    test('resuelve cross-refs entre items por clave en pasadas sucesivas', function () {
        $items = [
            ['id' => 1, 'clave' => 'X', 'cantidad' => 0.0, 'p_unit' => 0.0, 'formula_cantidad' => '2', 'formula_p_unit' => '10'],
            ['id' => 2, 'clave' => null, 'cantidad' => 0.0, 'p_unit' => 0.0, 'formula_cantidad' => '1', 'formula_p_unit' => 'importe_X * 0.1'],
        ];

        $r = $this->calc->evaluarItems($items, []);

        expect($r[1]['cantidad'])->toEqualWithDelta(2.0, 1e-9)
            ->and($r[1]['p_unit'])->toEqualWithDelta(10.0, 1e-9)
            ->and($r[2]['p_unit'])->toEqualWithDelta(2.0, 1e-9); // importe_X = 20 → ×0.1
    });

    test('item con variable inexistente conserva su valor guardado', function () {
        $items = [
            ['id' => 1, 'clave' => null, 'cantidad' => 7.0, 'p_unit' => 3.0, 'formula_cantidad' => 'no_existe', 'formula_p_unit' => null],
        ];

        $r = $this->calc->evaluarItems($items, ['dias' => 5]);

        expect($r[1]['cantidad'])->toEqualWithDelta(7.0, 1e-9)
            ->and($r[1]['p_unit'])->toEqualWithDelta(3.0, 1e-9);
    });

    test('roundup está disponible en las fórmulas', function () {
        $items = [
            ['id' => 1, 'clave' => null, 'cantidad' => 0.0, 'p_unit' => 0.0, 'formula_cantidad' => 'roundup(personas/5, 0)', 'formula_p_unit' => null],
        ];

        $r = $this->calc->evaluarItems($items, ['personas' => 11]);

        expect($r[1]['cantidad'])->toEqualWithDelta(3.0, 1e-9); // ceil(11/5) = 3
    });
});

describe('construirContexto', function () {
    test('expone las variables de la obra (grupos, personas, semanas, días)', function () {
        $obra = Obra::factory()->create(['num_grupos' => 2, 'factor_contratista' => 1.15]);
        $oficial = PersonalCategoria::factory()->create(['codigo' => 'OFICIAL', 'sueldo_semanal' => 1000]);
        ObraCuadrillaGlobal::factory()->create(['obra_id' => $obra->id, 'categoria_id' => $oficial->id, 'cantidad_por_grupo' => 3]);

        $ctx = $this->calc->construirContexto($obra->fresh());

        expect($ctx)->toHaveKeys(['grupos', 'personas', 'personas_totales', 'semanas', 'meses', 'dias', 'kg_obra', 'mo_montaje', 'camiones_total'])
            ->and($ctx['grupos'])->toEqualWithDelta(2.0, 1e-9)
            ->and($ctx['personas_totales'])->toEqualWithDelta(6.0, 1e-9);
    });
});

describe('recalcular', function () {
    test('persiste cantidad/p_unit cuando la fórmula difiere del valor guardado', function () {
        $obra = Obra::factory()->create(['num_grupos' => 3]);
        $item = ObraFleteViatico::factory()->create([
            'obra_id' => $obra->id, 'grupo' => 'VIATICOS',
            'cantidad' => 0, 'p_unit' => 50, 'formula_cantidad' => 'grupos', 'formula_p_unit' => null,
        ]);

        $hubo = $this->calc->recalcular($obra->fresh());

        expect($hubo)->toBeTrue();
        expect((float) $item->fresh()->cantidad)->toEqualWithDelta(3.0, 1e-9)
            ->and((float) $item->fresh()->p_unit)->toEqualWithDelta(50.0, 1e-9); // sin fórmula → intacto
    });

    test('no escribe nada si no hay items', function () {
        $obra = Obra::factory()->create();

        expect($this->calc->recalcular($obra))->toBeFalse();
    });
});
