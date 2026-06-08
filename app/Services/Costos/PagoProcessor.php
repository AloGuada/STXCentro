<?php

namespace App\Services\Costos;

use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\PagoEstatus;
use App\Enums\Costos\SolicitudPagoEstatus;
use App\Mail\ComplementoPendienteMail;
use App\Models\Costos\Factura;
use App\Models\Costos\Pago;
use App\Models\Costos\SolicitudPago;
use App\Models\User;
use App\Notifications\Costos\ProveedorBloqueadoNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Completa un pago ya marcado como pagado y propaga el efecto: cascada de
 * parcialidades hacia el pago padre y, al cerrarse, marca el pagable (factura →
 * recalcula la OC + obligación de complemento PPD; solicitud de pago → pagada).
 */
class PagoProcessor
{
    public function __construct(private readonly ComplementoPagoService $complementos) {}

    /**
     * Llamar DESPUÉS de marcar `$pago` como pagado. Si es parcialidad, intenta
     * cerrar el pago padre; si es raíz, marca su pagable como pagado.
     */
    public function completar(Pago $pago): void
    {
        if ($pago->esHijo()) {
            $this->checkAndMarkParentAsPaid($pago->pagoPadre);
        } else {
            $this->markPagableAsPaid($pago);
        }
    }

    private function checkAndMarkParentAsPaid(Pago $parent): void
    {
        $pendientes = $parent->pagosParciales()->where('estatus', '!=', 'pagado')->count();

        if ($pendientes > 0) {
            return;
        }

        $parent->update(['fecha_pago_realizada' => now()]);
        $parent->transitionTo(PagoEstatus::Pagado);

        if ($parent->esHijo()) {
            $this->checkAndMarkParentAsPaid($parent->pagoPadre);
        } else {
            $this->markPagableAsPaid($parent);
        }
    }

    private function markPagableAsPaid(Pago $pago): void
    {
        $pagable = $pago->pagable;

        if (! $pagable) {
            return;
        }

        if ($pagable instanceof Factura) {
            $pagable->transitionTo(FacturaEstatus::Pagada);
            $pagable->ordenCompra->recalcularEstatus();
            $this->generarObligacionComplemento($pago, $pagable);
        } elseif ($pagable instanceof SolicitudPago) {
            $pagable->update(['fecha_pago_realizada' => now()]);
            $pagable->transitionTo(SolicitudPagoEstatus::Pagada);
        }
    }

    /**
     * Para facturas PPD, genera la obligación de complemento de pago, avisa al
     * proveedor y, si con ella el proveedor queda bloqueado por primera vez,
     * notifica al área de pagos.
     */
    private function generarObligacionComplemento(Pago $pago, Factura $factura): void
    {
        if (! $factura->esPpd()) {
            return;
        }

        $proveedor = $factura->proveedor;
        $estabaBloqueado = $proveedor?->bloqueadoPorComplemento() ?? false;

        $obligacion = $this->complementos->generarObligacion($pago);

        if (! $obligacion || ! $proveedor) {
            return;
        }

        if ($proveedor->email) {
            Mail::to($proveedor->email)->send(new ComplementoPendienteMail($obligacion, $proveedor));
        }

        if (! $estabaBloqueado) {
            try {
                $destinatarios = User::permission('costos.pagos.programar')->get();
            } catch (\Throwable) {
                $destinatarios = collect();
            }

            if ($destinatarios->isNotEmpty()) {
                Notification::send($destinatarios, new ProveedorBloqueadoNotification($proveedor, $obligacion));
            }
        }
    }
}
