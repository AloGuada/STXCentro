<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RubroAfectado;
use App\Models\Costos\RubroMovimiento;
use App\Services\Costos\ApartadoPresupuestal;

function apartado(): ApartadoPresupuestal
{
    return app(ApartadoPresupuestal::class);
}

describe('AcumuladoLedger', function () {
    test('apartar registra un cargo con saldo antes y despues', function () {
        $rubro = ObraRubro::factory()->create(['presupuestado' => 10000, 'acumulado' => 0]);
        $req = Requisicion::factory()->create();

        apartado()->apartarDocumento($req, [['obra_rubro_id' => $rubro->id, 'monto' => 3000]]);

        $mov = RubroMovimiento::where('obra_rubro_id', $rubro->id)->sole();
        expect($mov->tipo)->toBe('cargo')
            ->and((float) $mov->saldo_antes)->toBe(0.0)
            ->and((float) $mov->saldo_despues)->toBe(3000.0)
            ->and((float) $mov->monto)->toBe(3000.0)
            ->and((float) $rubro->fresh()->acumulado)->toBe(3000.0);
    });

    test('convertir a permanente no genera nuevo movimiento', function () {
        $rubro = ObraRubro::factory()->create(['presupuestado' => 10000]);
        $req = Requisicion::factory()->create();
        apartado()->apartarDocumento($req, [['obra_rubro_id' => $rubro->id, 'monto' => 3000]]);

        apartado()->convertirAPermanente($req);

        expect(RubroMovimiento::where('obra_rubro_id', $rubro->id)->count())->toBe(1)
            ->and((float) $rubro->fresh()->acumulado)->toBe(3000.0);
    });

    test('cancelar registra un reverso y continua la cadena de saldos', function () {
        $rubro = ObraRubro::factory()->create(['presupuestado' => 10000]);
        $req = Requisicion::factory()->create();
        apartado()->apartarDocumento($req, [['obra_rubro_id' => $rubro->id, 'monto' => 3000]]);

        apartado()->cancelarApartadosDe($req, 'prueba');

        $movs = RubroMovimiento::where('obra_rubro_id', $rubro->id)->orderBy('id')->get();
        expect($movs)->toHaveCount(2)
            ->and($movs[1]->tipo)->toBe('reverso')
            ->and((float) $movs[1]->saldo_antes)->toBe((float) $movs[0]->saldo_despues)
            ->and((float) $movs[1]->saldo_despues)->toBe(0.0)
            ->and((float) $rubro->fresh()->acumulado)->toBe(0.0);
    });

    test('liberar vencidos registra un reverso', function () {
        $rubro = ObraRubro::factory()->create(['presupuestado' => 10000]);
        $req = Requisicion::factory()->create();
        apartado()->apartarDocumento($req, [['obra_rubro_id' => $rubro->id, 'monto' => 3000]]);

        RubroAfectado::where('obra_rubro_id', $rubro->id)->update(['apartado_hasta' => now()->subDay()]);
        apartado()->liberarVencidos();

        $movs = RubroMovimiento::where('obra_rubro_id', $rubro->id)->orderBy('id')->get();
        expect($movs->last()->tipo)->toBe('reverso')
            ->and($movs->last()->motivo)->toBe('apartado vencido')
            ->and((float) $rubro->fresh()->acumulado)->toBe(0.0);
    });

    test('el ultimo saldo_despues siempre coincide con el acumulado vigente', function () {
        $rubro = ObraRubro::factory()->create(['presupuestado' => 10000]);
        apartado()->apartarDocumento(Requisicion::factory()->create(), [['obra_rubro_id' => $rubro->id, 'monto' => 3000]]);
        apartado()->apartarDocumento(Requisicion::factory()->create(), [['obra_rubro_id' => $rubro->id, 'monto' => 1500]]);

        $ultimo = RubroMovimiento::where('obra_rubro_id', $rubro->id)->orderByDesc('id')->first();
        expect((float) $ultimo->saldo_despues)->toBe((float) $rubro->fresh()->acumulado)
            ->and((float) $rubro->fresh()->acumulado)->toBe(4500.0);
    });

    test('el movimiento de cargo queda ligado a su rubro afectado', function () {
        $rubro = ObraRubro::factory()->create(['presupuestado' => 10000]);
        $req = Requisicion::factory()->create();
        apartado()->apartarDocumento($req, [['obra_rubro_id' => $rubro->id, 'monto' => 3000]]);

        $ra = RubroAfectado::where('obra_rubro_id', $rubro->id)->sole();
        $mov = RubroMovimiento::where('obra_rubro_id', $rubro->id)->sole();
        expect($mov->rubro_afectado_id)->toBe($ra->id);
    });
});
