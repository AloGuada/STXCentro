<?php

namespace App\Services\Costos;

use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\OrdenCompraEstatus;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;

/**
 * Calcula y aplica el estatus de una orden de compra a partir del estado
 * agregado de sus facturas activas y la presencia de recepciones de almacén.
 *
 * NOTA: el recálculo automático aplica la transición vía ->update() directo
 * (no transitionTo()), por decisión deliberada de la máquina de estados — ver
 * HasStateMachine y OrdenCompraEstatus::allowedTransitions().
 */
class OrdenCompraEstadoService
{
    /**
     * Determina el estatus que corresponde a la OC según sus facturas activas y
     * entregas. Devuelve null cuando la OC está cancelada (estado terminal que
     * no debe recalcularse).
     *
     * Reglas (en orden):
     *  - sin facturas en pipeline, sin entregas → pendiente_entrega
     *  - sin facturas en pipeline, con entregas → pendiente_factura
     *  - todas las facturas pagadas             → pagada
     *  - todas en pago o pagadas                → pendiente_pago
     *  - default (con facturas en pipeline)     → pendiente_aprobacion
     *
     * Las facturas en `pendiente_recepcion` (subidas pero aún sin recepción +
     * comprobante) no cuentan como "en pipeline": la OC sigue en fase de
     * recepción hasta que al menos una factura avance a aprobación.
     */
    public function calcular(OrdenCompra $orden): ?OrdenCompraEstatus
    {
        if ($orden->estatus === OrdenCompraEstatus::Cancelada) {
            return null;
        }

        $facturas = $orden->facturas()
            ->where('estatus', '!=', FacturaEstatus::Cancelada->value)
            ->get();

        $enPipeline = $facturas->reject(fn (Factura $f) => $f->estatus === FacturaEstatus::PendienteRecepcion);

        if ($enPipeline->isEmpty()) {
            return $orden->entregas()->exists()
                ? OrdenCompraEstatus::PendienteFactura
                : OrdenCompraEstatus::PendienteEntrega;
        }

        if ($facturas->every(fn (Factura $f) => $f->estatus === FacturaEstatus::Pagada)) {
            return OrdenCompraEstatus::Pagada;
        }

        if ($facturas->every(fn (Factura $f) => in_array($f->estatus, [FacturaEstatus::PendientePago, FacturaEstatus::Pagada], true))) {
            return OrdenCompraEstatus::PendientePago;
        }

        return OrdenCompraEstatus::PendienteAprobacion;
    }

    /**
     * Recalcula y persiste el estatus de la OC. No-op si está cancelada.
     */
    public function recalcular(OrdenCompra $orden): void
    {
        $nuevo = $this->calcular($orden);

        if ($nuevo !== null) {
            $orden->update(['estatus' => $nuevo->value]);
        }
    }
}
