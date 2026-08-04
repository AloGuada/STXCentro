<?php

namespace App\Models\Costos\Concerns;

use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Costos\ObraRubro;
use App\Services\Costos\ApartadoPresupuestal;
use Illuminate\Support\Facades\Auth;

/**
 * Aplica impacto presupuestal permanente: por cada detalle registra un cargo
 * (Aplicado) sobre el obra_rubro correspondiente vía la primitiva compartida
 * {@see ApartadoPresupuestal::aplicarCargo()} (valida sobregiro, incrementa el
 * acumulado y crea el RubroAfectado).
 *
 * El modelo que use el trait debe exponer la relación `detalles` (con
 * `obra_rubro_id` y `subtotal`) y definir cómo describir cada afectación.
 */
trait AfectaPresupuesto
{
    public function aplicarImpactoPresupuestal(?string $userId = null): void
    {
        $userId = $userId ?? Auth::id();
        $apartado = app(ApartadoPresupuestal::class);

        foreach ($this->detalles as $detalle) {
            // Partidas sin centro de costos (requisición "sin obra"): el gasto
            // vive fuera del presupuesto y no genera afectación alguna.
            if (empty($detalle->obra_rubro_id)) {
                continue;
            }

            $obraRubro = ObraRubro::find($detalle->obra_rubro_id);

            $apartado->aplicarCargo(
                entrada: $this,
                obraRubroId: (int) $detalle->obra_rubro_id,
                monto: (float) $detalle->subtotal,
                estatus: RubroAfectadoEstatus::Aplicado,
                descripcion: $this->descripcionAfectacion($detalle, $obraRubro),
                userId: $userId,
                moneda: $this->monedaAfectacion(),
                tc: $this->tipo_cambio ? (float) $this->tipo_cambio : null,
            );
        }
    }

    /**
     * Moneda del documento para convertir el impacto a MXN. La OC la expone en
     * `moneda`, la SolicitudPago en `tipo_moneda`; solo una existe por modelo.
     */
    protected function monedaAfectacion(): string
    {
        return $this->moneda ?? $this->tipo_moneda ?? 'mxn';
    }

    abstract protected function descripcionAfectacion(object $detalle, ObraRubro $obraRubro): ?string;
}
