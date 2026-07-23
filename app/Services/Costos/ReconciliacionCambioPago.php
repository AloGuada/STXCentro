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
 * El delta que se concilia es solo el de ESTE pago: real − (monto_pago × TC de
 * referencia guardado en el pago). Así los pagos parciales y las múltiples
 * facturas por OC ajustan cada uno su propia porción, sin sobre-conciliar la
 * entidad completa. La ponderación entre rubros usa la participación de cada uno
 * en el ejercido de la entidad.
 */
class ReconciliacionCambioPago
{
    public function __construct(private readonly AcumuladoLedger $ledger) {}

    public function reconciliar(Pago $pago, float $montoRealMxn): void
    {
        $tcReferencia = (float) $pago->tipo_cambio;
        $tcPago = (float) $pago->monto_pago > 0.0
            ? round($montoRealMxn / (float) $pago->monto_pago, 4)
            : $tcReferencia;

        if (strtolower((string) $pago->moneda) === 'mxn') {
            $pago->update(['monto_mxn' => $montoRealMxn, 'tipo_cambio' => 1]);

            return;
        }

        $entidad = $this->entidadPresupuestal($pago);

        if ($entidad === null) {
            $pago->update(['monto_mxn' => $montoRealMxn, 'tipo_cambio' => $tcPago]);

            return;
        }

        DB::transaction(function () use ($pago, $montoRealMxn, $tcPago, $tcReferencia, $entidad) {
            $afectados = RubroAfectado::query()
                ->where('entrada_type', $entidad::class)
                ->where('entrada_id', $entidad->getKey())
                ->where('estatus', RubroAfectadoEstatus::Aplicado->value)
                ->lockForUpdate()
                ->get();

            $pesoBase = (float) $afectados->sum(fn (RubroAfectado $ra): float => (float) $ra->monto);

            if ($afectados->isEmpty() || $pesoBase <= 0.0) {
                $pago->update(['monto_mxn' => $montoRealMxn, 'tipo_cambio' => $tcPago]);

                return;
            }

            // El delta a conciliar es solo la diferencia FX de ESTE pago (real −
            // su referencia = monto_pago × TC guardado), no del total de la
            // entidad: así los pagos parciales y las múltiples facturas por OC
            // ajustan cada uno su porción sin sobre-conciliar.
            $referenciaPago = round((float) $pago->monto_pago * $tcReferencia, 2);
            $delta = round($montoRealMxn - $referenciaPago, 2);
            $repartido = 0.0;
            $ultimo = $afectados->count() - 1;

            foreach ($afectados->values() as $i => $ra) {
                // El último rubro absorbe el residuo de redondeo para que
                // Σ deltas == delta total exacto.
                $deltaRubro = $i === $ultimo
                    ? round($delta - $repartido, 2)
                    : round($delta * ((float) $ra->monto / $pesoBase), 2);
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
