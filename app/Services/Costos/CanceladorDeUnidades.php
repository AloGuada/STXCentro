<?php

namespace App\Services\Costos;

use App\Enums\Costos\CancelacionUnidadesEstatus;
use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\OrdenCompraEstatus;
use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\OrdenCompraDetalleCancelacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancelar unidades de una orden de compra ya emitida.
 *
 * El caso que lo pidió: una partida de 100 piezas con 60 recibidas donde el
 * proveedor ya no va a surtir las 40 que faltan. La cantidad pedida no se
 * toca; lo cancelado se anota aparte y se descuenta del saldo por recibir, del
 * denominador de los porcentajes y del total de la orden.
 *
 * Nada surte efecto sin la autorización del jefe de compras: `solicitar()`
 * sólo deja el registro pendiente —y eso ya reporta la orden como pendiente de
 * aprobación—, y es `autorizar()` quien escribe la cantidad, baja el total y
 * revierte el presupuesto.
 */
class CanceladorDeUnidades
{
    public function __construct(
        private readonly ApartadoPresupuestal $presupuesto,
        private readonly OrdenCompraEstadoService $estado,
    ) {}

    /**
     * Lo que todavía se puede cancelar de una partida: lo pedido menos lo que
     * ya llegó y menos lo que otra cancelación tiene tomado.
     *
     * Lo recibido se cuenta **bruto**, sin descontar devoluciones, igual que el
     * tope de {@see RegistradorRecepcion::validarSaldos()}: si aquí se contara
     * el neto, una devolución abriría espacio para cancelar unidades que sí
     * llegaron.
     */
    public function cancelable(OrdenCompraDetalle $partida, ?int $excluyendo = null): float
    {
        $recibido = (float) EntregaDetalle::query()
            ->where('orden_compra_detalle_id', $partida->getKey())
            ->whereHas('entrega', fn ($q) => $q->activa())
            ->sum('cantidad_recibida');

        $pendientes = (float) OrdenCompraDetalleCancelacion::query()
            ->where('orden_compra_detalle_id', $partida->getKey())
            ->when($excluyendo !== null, fn ($q) => $q->whereKeyNot($excluyendo))
            ->pendiente()
            ->sum('cantidad');

        return max(0.0, (float) $partida->cantidad - (float) $partida->cantidad_cancelada - $recibido - $pendientes);
    }

    /**
     * Deja la cancelación pendiente de que la autorice el jefe de compras.
     *
     * @throws ValidationException
     */
    public function solicitar(OrdenCompraDetalle $partida, float $cantidad, string $motivo, ?string $userId = null): OrdenCompraDetalleCancelacion
    {
        $orden = $partida->ordenCompra;

        if ($orden->estatus === OrdenCompraEstatus::Cancelada) {
            throw ValidationException::withMessages(['cantidad' => 'La orden está cancelada completa.']);
        }

        if ($cantidad <= 0) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad a cancelar debe ser mayor que cero.']);
        }

        $cancelable = $this->cancelable($partida);

        if ($cantidad > $cancelable + (float) config('costos.epsilon_cantidad')) {
            throw ValidationException::withMessages([
                'cantidad' => sprintf(
                    'Sólo quedan %.2f %s por cancelar en la partida "%s".',
                    $cancelable,
                    $partida->unidad,
                    $partida->descripcion,
                ),
            ]);
        }

        // Una factura viva puede cubrir esas unidades: cancelarlas dejaría a la
        // orden facturada por más de lo que pidió.
        if ($this->tieneFacturaActiva($partida)) {
            throw ValidationException::withMessages([
                'cantidad' => 'La orden tiene facturas activas. Cancele primero la factura de esas unidades.',
            ]);
        }

        return DB::transaction(function () use ($partida, $cantidad, $motivo, $userId): OrdenCompraDetalleCancelacion {
            $cancelacion = OrdenCompraDetalleCancelacion::create([
                'orden_compra_detalle_id' => $partida->getKey(),
                'cantidad' => $cantidad,
                'motivo' => $motivo,
                'estatus' => CancelacionUnidadesEstatus::Pendiente->value,
                'solicitado_por' => $userId ?? Auth::id(),
            ]);

            // Mientras esté pendiente, la orden se reporta pendiente de aprobación.
            $this->estado->recalcular($partida->ordenCompra);

            return $cancelacion;
        });
    }

    /**
     * El jefe de compras autoriza: aquí sí se cancelan las unidades.
     *
     * @throws ValidationException
     */
    public function autorizar(OrdenCompraDetalleCancelacion $cancelacion, ?string $userId = null): void
    {
        if ($cancelacion->estatus !== CancelacionUnidadesEstatus::Pendiente) {
            throw ValidationException::withMessages(['estatus' => 'Esta cancelación ya fue resuelta.']);
        }

        DB::transaction(function () use ($cancelacion, $userId): void {
            $partida = $cancelacion->detalle()->lockForUpdate()->firstOrFail();
            $orden = $partida->ordenCompra;
            $cantidad = (float) $cancelacion->cantidad;

            // El tope se vuelve a medir al autorizar: entre la solicitud y la
            // firma pudo llegar material.
            if ($cantidad > $this->cancelable($partida, excluyendo: (int) $cancelacion->getKey()) + (float) config('costos.epsilon_cantidad')) {
                throw ValidationException::withMessages([
                    'cantidad' => 'Ya no alcanza el saldo de la partida: llegó material despues de solicitar la cancelación.',
                ]);
            }

            $partida->update([
                'cantidad_cancelada' => (float) $partida->cantidad_cancelada + $cantidad,
                'subtotal' => round(((float) $partida->cantidad - (float) $partida->cantidad_cancelada - $cantidad) * (float) $partida->precio_unitario, 2),
            ]);

            $this->bajarTotalDeLaOrden($partida, $cantidad);
            $this->revertirPresupuesto($partida, $cantidad, $cancelacion, $userId);

            $cancelacion->transitionTo(CancelacionUnidadesEstatus::Autorizada);
            $cancelacion->update([
                'autorizado_por' => $userId ?? Auth::id(),
                'autorizado_at' => now(),
            ]);

            $this->estado->recalcular($orden->fresh());
        });
    }

    /**
     * El jefe de compras rechaza: no se toca cantidad ni presupuesto.
     *
     * @throws ValidationException
     */
    public function rechazar(OrdenCompraDetalleCancelacion $cancelacion, string $motivo, ?string $userId = null): void
    {
        if ($cancelacion->estatus !== CancelacionUnidadesEstatus::Pendiente) {
            throw ValidationException::withMessages(['estatus' => 'Esta cancelación ya fue resuelta.']);
        }

        DB::transaction(function () use ($cancelacion, $motivo, $userId): void {
            $cancelacion->transitionTo(CancelacionUnidadesEstatus::Rechazada);
            $cancelacion->update([
                'motivo_rechazo' => $motivo,
                'autorizado_por' => $userId ?? Auth::id(),
                'autorizado_at' => now(),
            ]);

            $this->estado->recalcular($cancelacion->detalle->ordenCompra);
        });
    }

    /**
     * El total de la orden baja por el importe cancelado. Se resta el delta en
     * lugar de recalcular el total desde las líneas: una orden capturada a mano
     * puede traer conceptos que no salen de las partidas, y recalcular los
     * borraría.
     */
    private function bajarTotalDeLaOrden(OrdenCompraDetalle $partida, float $cantidad): void
    {
        $orden = $partida->ordenCompra;
        $importe = round($cantidad * (float) $partida->precio_unitario, 2);
        $conIva = $partida->sin_impuestos ? $importe : round($importe * (1 + (float) config('costos.iva_rate')), 2);

        $orden->update(['total' => max(0, round((float) $orden->total - $conIva, 2))]);
    }

    /**
     * Las unidades canceladas dejan de pesar en el presupuesto: reversa parcial
     * al tipo de cambio con el que la orden aplicó su cargo.
     *
     * Se permite sobregiro porque es una reversa —baja el comprometido— y una
     * partida sin centro de costos no mueve nada, igual que al aplicar el cargo.
     */
    private function revertirPresupuesto(OrdenCompraDetalle $partida, float $cantidad, OrdenCompraDetalleCancelacion $cancelacion, ?string $userId): void
    {
        if ($partida->obra_rubro_id === null) {
            return;
        }

        $orden = $partida->ordenCompra;

        $this->presupuesto->aplicarCargo(
            entrada: $orden,
            obraRubroId: (int) $partida->obra_rubro_id,
            monto: -round($cantidad * (float) $partida->precio_unitario, 2),
            estatus: RubroAfectadoEstatus::Aplicado,
            descripcion: "Cancelación de {$cantidad} {$partida->unidad} · OC {$orden->folio} · cancelación #{$cancelacion->getKey()}",
            userId: $userId ?? Auth::id(),
            allowSobregiro: true,
            moneda: (string) $orden->moneda,
            tc: (float) $orden->tipo_cambio,
        );
    }

    /**
     * ¿La orden de esta partida tiene alguna factura que no esté cancelada?
     */
    private function tieneFacturaActiva(OrdenCompraDetalle $partida): bool
    {
        return $partida->ordenCompra
            ->facturas()
            ->where('estatus', '!=', FacturaEstatus::Cancelada->value)
            ->exists();
    }
}
