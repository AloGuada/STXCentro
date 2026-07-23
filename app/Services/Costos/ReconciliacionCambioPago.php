<?php

namespace App\Services\Costos;

use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Costos\Factura;
use App\Models\Costos\Pago;
use App\Models\Costos\RubroAfectado;
use App\Models\Costos\SolicitudPago;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Segundo momento del tipo de cambio: al comprobar un pago en divisa se conoce
 * el monto real en MXN que salió del banco. Concilia el presupuesto ejercido
 * (que se cargó con el TC de referencia) contra ese real, prorrateando el delta
 * entre los rubros afectados del documento vía el {@see AcumuladoLedger}.
 *
 * Supuesto v1: el pago cubre por completo la entidad presupuestal (una OC / una
 * SolicitudPago con un solo pago). Para pagos parciales o múltiples facturas por
 * OC habría que escalar el delta por la porción pagada (pendiente).
 */
class ReconciliacionCambioPago
{
    public function __construct(private readonly AcumuladoLedger $ledger) {}

    public function reconciliar(Pago $pago, float $montoRealMxn): void
    {
        $tcPago = (float) $pago->monto_pago > 0.0
            ? round($montoRealMxn / (float) $pago->monto_pago, 4)
            : (float) $pago->tipo_cambio;

        if (strtolower((string) $pago->moneda) === 'mxn') {
            $pago->update(['monto_mxn' => $montoRealMxn, 'tipo_cambio' => 1]);

            return;
        }

        $entidad = $this->entidadPresupuestal($pago);

        if ($entidad === null) {
            $pago->update(['monto_mxn' => $montoRealMxn, 'tipo_cambio' => $tcPago]);

            return;
        }

        DB::transaction(function () use ($pago, $montoRealMxn, $tcPago, $entidad) {
            $afectados = RubroAfectado::query()
                ->where('entrada_type', $entidad::class)
                ->where('entrada_id', $entidad->getKey())
                ->where('estatus', RubroAfectadoEstatus::Aplicado->value)
                ->lockForUpdate()
                ->get();

            $referencia = (float) $afectados->sum(fn (RubroAfectado $ra): float => (float) $ra->monto);

            if ($afectados->isEmpty() || $referencia <= 0.0) {
                $pago->update(['monto_mxn' => $montoRealMxn, 'tipo_cambio' => $tcPago]);

                return;
            }

            $delta = round($montoRealMxn - $referencia, 2);
            $repartido = 0.0;
            $ultimo = $afectados->count() - 1;

            foreach ($afectados->values() as $i => $ra) {
                // El último rubro absorbe el residuo de redondeo para que
                // Σ deltas == delta total exacto.
                $deltaRubro = $i === $ultimo
                    ? round($delta - $repartido, 2)
                    : round($delta * ((float) $ra->monto / $referencia), 2);
                $repartido += $deltaRubro;

                if (abs($deltaRubro) >= 0.005) {
                    $this->ledger->registrarPorId(
                        (int) $ra->obra_rubro_id,
                        $deltaRubro,
                        $ra->id,
                        "Reconciliación TC pago {$pago->folio}",
                    );
                }

                $nuevoMonto = round((float) $ra->monto + $deltaRubro, 2);
                $tcReal = (float) $ra->monto_origen > 0.0
                    ? round($nuevoMonto / (float) $ra->monto_origen, 6)
                    : (float) $ra->tipo_cambio;

                $ra->update(['monto' => $nuevoMonto, 'tipo_cambio' => $tcReal]);
            }

            $pago->update(['monto_mxn' => $montoRealMxn, 'tipo_cambio' => $tcPago]);
        });
    }

    /**
     * Entidad que cargó el presupuesto para este pago: la OC (si el pagable es
     * una Factura o una SolicitudPago originada en OC) o la SolicitudPago misma.
     */
    private function entidadPresupuestal(Pago $pago): ?Model
    {
        $pagable = $pago->pagable;

        if ($pagable instanceof Factura) {
            return $pagable->ordenCompra;
        }

        if ($pagable instanceof SolicitudPago) {
            return $pagable->orden_compra_id ? $pagable->ordenCompra : $pagable;
        }

        return null;
    }
}
