<?php

namespace App\Notifications\Costos;

use App\Models\Costos\ComplementoPago;
use App\Models\Proveedor;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificación vía canal `database` para alertar al área de pagos cuando un
 * proveedor queda bloqueado por una obligación de complemento de pago pendiente.
 */
class ProveedorBloqueadoNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Proveedor $proveedor,
        public readonly ComplementoPago $complemento,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'proveedor_bloqueado_complemento',
            'proveedor_id' => $this->proveedor->id,
            'proveedor_razon_social' => $this->proveedor->razon_social,
            'complemento_id' => $this->complemento->id,
            'complemento_folio' => $this->complemento->folio,
            'factura_id' => $this->complemento->factura_id,
            'monto_pago' => (float) $this->complemento->monto_pago,
            'mensaje' => sprintf(
                'El proveedor "%s" quedó bloqueado por un complemento de pago pendiente (%s).',
                $this->proveedor->razon_social,
                $this->complemento->folio,
            ),
        ];
    }
}
