<?php

use App\Enums\Costos\RubroAfectadoEstatus;
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
    test('apartar no genera movimiento del ledger: solo reserva viva', function () {
        $rubro = ObraRubro::factory()->create(['presupuestado' => 10000, 'acumulado' => 0]);
        $req = Requisicion::factory()->create();

        apartado()->apartarDocumento($req, [['obra_rubro_id' => $rubro->id, 'monto' => 3000]]);

        expect(RubroMovimiento::where('obra_rubro_id', $rubro->id)->count())->toBe(0)
            ->and((float) $rubro->fresh()->acumulado)->toBe(0.0)
            ->and((float) $rubro->fresh()->apartado)->toBe(3000.0);
    });

    test('convertir a permanente mueve la reserva al ejercido y registra el cargo', function () {
        $rubro = ObraRubro::factory()->create(['presupuestado' => 10000]);
        $req = Requisicion::factory()->create();
        apartado()->apartarDocumento($req, [['obra_rubro_id' => $rubro->id, 'monto' => 3000]]);

        apartado()->convertirAPermanente($req);

        $mov = RubroMovimiento::where('obra_rubro_id', $rubro->id)->sole();
        expect($mov->tipo)->toBe('cargo')
            ->and((float) $mov->saldo_antes)->toBe(0.0)
            ->and((float) $mov->saldo_despues)->toBe(3000.0)
            ->and((float) $rubro->fresh()->acumulado)->toBe(3000.0)
            ->and((float) $rubro->fresh()->apartado)->toBe(0.0);
    });

    test('cancelar un apartado baja la reserva sin tocar el ledger', function () {
        $rubro = ObraRubro::factory()->create(['presupuestado' => 10000]);
        $req = Requisicion::factory()->create();
        apartado()->apartarDocumento($req, [['obra_rubro_id' => $rubro->id, 'monto' => 3000]]);

        apartado()->cancelarApartadosDe($req, 'prueba');

        expect(RubroMovimiento::where('obra_rubro_id', $rubro->id)->count())->toBe(0)
            ->and((float) $rubro->fresh()->acumulado)->toBe(0.0)
            ->and((float) $rubro->fresh()->apartado)->toBe(0.0);
    });

    test('cancelar un aplicado registra un reverso y continua la cadena de saldos', function () {
        $rubro = ObraRubro::factory()->create(['presupuestado' => 10000]);
        $req = Requisicion::factory()->create();
        apartado()->apartarDocumento($req, [['obra_rubro_id' => $rubro->id, 'monto' => 3000]]);
        apartado()->convertirAPermanente($req);

        apartado()->cancelarApartadosDe($req, 'prueba');

        $movs = RubroMovimiento::where('obra_rubro_id', $rubro->id)->orderBy('id')->get();
        expect($movs)->toHaveCount(2)
            ->and($movs[1]->tipo)->toBe('reverso')
            ->and((float) $movs[1]->saldo_antes)->toBe((float) $movs[0]->saldo_despues)
            ->and((float) $movs[1]->saldo_despues)->toBe(0.0)
            ->and((float) $rubro->fresh()->acumulado)->toBe(0.0);
    });

    test('liberar vencidos baja la reserva y no toca el ejercido', function () {
        $rubro = ObraRubro::factory()->create(['presupuestado' => 10000]);
        $req = Requisicion::factory()->create();
        apartado()->apartarDocumento($req, [['obra_rubro_id' => $rubro->id, 'monto' => 3000]]);

        RubroAfectado::where('obra_rubro_id', $rubro->id)->update(['apartado_hasta' => now()->subDay()]);
        apartado()->liberarVencidos();

        expect(RubroMovimiento::where('obra_rubro_id', $rubro->id)->count())->toBe(0)
            ->and((float) $rubro->fresh()->acumulado)->toBe(0.0)
            ->and((float) $rubro->fresh()->apartado)->toBe(0.0);
    });

    test('el ultimo saldo_despues siempre coincide con el acumulado vigente', function () {
        $rubro = ObraRubro::factory()->create(['presupuestado' => 10000]);
        apartado()->aplicarCargo(
            entrada: Requisicion::factory()->create(),
            obraRubroId: $rubro->id,
            monto: 3000,
            estatus: RubroAfectadoEstatus::Aplicado,
        );
        apartado()->aplicarCargo(
            entrada: Requisicion::factory()->create(),
            obraRubroId: $rubro->id,
            monto: 1500,
            estatus: RubroAfectadoEstatus::Aplicado,
        );

        $ultimo = RubroMovimiento::where('obra_rubro_id', $rubro->id)->orderByDesc('id')->first();
        expect((float) $ultimo->saldo_despues)->toBe((float) $rubro->fresh()->acumulado)
            ->and((float) $rubro->fresh()->acumulado)->toBe(4500.0);
    });

    test('el movimiento de cargo queda ligado a su rubro afectado', function () {
        $rubro = ObraRubro::factory()->create(['presupuestado' => 10000]);
        $ra = apartado()->aplicarCargo(
            entrada: Requisicion::factory()->create(),
            obraRubroId: $rubro->id,
            monto: 3000,
            estatus: RubroAfectadoEstatus::Aplicado,
        );

        $mov = RubroMovimiento::where('obra_rubro_id', $rubro->id)->sole();
        expect($mov->rubro_afectado_id)->toBe($ra->id);
    });
});
