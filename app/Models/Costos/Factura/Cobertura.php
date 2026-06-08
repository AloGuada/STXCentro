<?php

namespace App\Models\Costos\Factura;

use App\Enums\Costos\FacturaEstatus;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\Factura;
use App\Models\Costos\FacturaDetalle;

/**
 * Calcula la cobertura de recepción de una factura: cuánto de cada partida ya
 * fue recibido por almacén (descontando lo facturado por facturas previas, FIFO)
 * y si la factura está completamente cubierta.
 *
 * Centraliza el cálculo que antes vivía disperso en el modelo Factura
 * (partidaCubierta + cobertura_completa + cobertura_por_partida).
 */
final class Cobertura
{
    public function __construct(private readonly Factura $factura) {}

    /**
     * Disponible para cubrir una partida = recibido_total − facturado_previo.
     */
    public function disponiblePara(FacturaDetalle $fd): float
    {
        return $this->cantidadRecibida($fd->orden_compra_detalle_id)
            - $this->cantidadFacturadaPrevia($fd->orden_compra_detalle_id);
    }

    /**
     * ¿La partida está cubierta por recepciones disponibles?
     */
    public function partidaCubierta(FacturaDetalle $fd): bool
    {
        return (float) $fd->cantidad <= $this->disponiblePara($fd) + config('costos.epsilon_cantidad');
    }

    /**
     * La factura está completamente cubierta si todas sus partidas tienen
     * recepción suficiente.
     */
    public function estaCompleta(): bool
    {
        $this->factura->loadMissing('detalles');

        if ($this->factura->detalles->isEmpty()) {
            return false;
        }

        return $this->factura->detalles->every(fn (FacturaDetalle $fd) => $this->partidaCubierta($fd));
    }

    /**
     * Snapshot para UI: por cada FacturaDetalle.id, cuánto disponible hay y si
     * está cubierta.
     *
     * @return array<int, array{disponible: float, cubierta: bool}>
     */
    public function porPartida(): array
    {
        $this->factura->loadMissing('detalles');

        $out = [];
        foreach ($this->factura->detalles as $fd) {
            $disponible = $this->disponiblePara($fd);
            $out[$fd->id] = [
                'disponible' => round(max(0, $disponible), 2),
                'cubierta' => (float) $fd->cantidad <= $disponible + config('costos.epsilon_cantidad'),
            ];
        }

        return $out;
    }

    /**
     * Cantidad recibida total de una partida de OC (todas las entregas).
     */
    private function cantidadRecibida(int $ocdId): float
    {
        return (float) EntregaDetalle::query()
            ->where('orden_compra_detalle_id', $ocdId)
            ->sum('cantidad_recibida');
    }

    /**
     * Cantidad ya facturada de una partida de OC por OTRAS facturas activas
     * (no canceladas) creadas antes que esta — cobertura cronológica FIFO.
     */
    private function cantidadFacturadaPrevia(int $ocdId): float
    {
        return (float) FacturaDetalle::query()
            ->where('orden_compra_detalle_id', $ocdId)
            ->whereHas('factura', function ($q) {
                $q->where('estatus', '!=', FacturaEstatus::Cancelada->value)
                    ->where('id', '!=', $this->factura->id ?? 0)
                    ->where('created_at', '<=', $this->factura->created_at ?? now());
            })
            ->sum('cantidad');
    }
}
