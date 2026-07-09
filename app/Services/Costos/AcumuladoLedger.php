<?php

namespace App\Services\Costos;

use App\Models\Costos\ObraRubro;
use App\Models\Costos\RubroMovimiento;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Única puerta de escritura del `acumulado` de un centro de costos.
 *
 * Toda mutación pasa por aquí: bloquea la fila (lockForUpdate), lee el saldo
 * anterior, aplica el delta y registra un {@see RubroMovimiento} con saldo antes
 * y después dentro de la misma transacción. Así el ledger nunca se desincroniza
 * del `acumulado`: el `saldo_despues` del último movimiento es, por construcción,
 * el `acumulado` vigente.
 *
 * Convención de signo: delta positivo = cargo, negativo = reverso.
 */
class AcumuladoLedger
{
    /**
     * Registra un movimiento sobre un centro de costos ya bloqueado por el
     * llamador (dentro de su propia transacción). Evita re-bloquear la fila.
     */
    public function registrar(
        ObraRubro $obraRubro,
        float $delta,
        ?int $rubroAfectadoId = null,
        ?string $motivo = null,
        ?string $userId = null,
    ): ObraRubro {
        $antes = (float) $obraRubro->acumulado;
        $despues = $antes + $delta;

        $obraRubro->update(['acumulado' => $despues]);

        RubroMovimiento::create([
            'obra_rubro_id' => $obraRubro->getKey(),
            'rubro_afectado_id' => $rubroAfectadoId,
            'tipo' => $delta >= 0 ? 'cargo' : 'reverso',
            'monto' => abs($delta),
            'saldo_antes' => $antes,
            'saldo_despues' => $despues,
            'motivo' => $motivo,
            'usuario_id' => $userId ?? Auth::id(),
        ]);

        return $obraRubro;
    }

    /**
     * Bloquea el centro de costos por id y registra el movimiento. Para los
     * llamadores que no tienen la fila bloqueada (controladores, reversos).
     */
    public function registrarPorId(
        int $obraRubroId,
        float $delta,
        ?int $rubroAfectadoId = null,
        ?string $motivo = null,
        ?string $userId = null,
    ): ObraRubro {
        return DB::transaction(fn (): ObraRubro => $this->registrar(
            ObraRubro::whereKey($obraRubroId)->lockForUpdate()->firstOrFail(),
            $delta,
            $rubroAfectadoId,
            $motivo,
            $userId,
        ));
    }
}
