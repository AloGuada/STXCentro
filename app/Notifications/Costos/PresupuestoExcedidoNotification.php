<?php

namespace App\Notifications\Costos;

use App\Models\Costos\ObraRubro;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/**
 * Notificacion via canal `database` para alertar a aprobadores cuando un
 * obra_rubro entra en sobregiro o cruza el umbral critico. La UI puede
 * leerla via `$user->unreadNotifications`.
 */
class PresupuestoExcedidoNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ObraRubro $obraRubro,
        public readonly float $montoIntentado,
        public readonly Model $entrada,
        public readonly string $nivel,
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
            'tipo' => 'presupuesto_excedido',
            'nivel' => $this->nivel, // 'sobregiro' | 'critico'
            'obra_rubro_id' => $this->obraRubro->id,
            'obra_descripcion' => $this->obraRubro->obra?->descripcion,
            'rubro_descripcion' => $this->obraRubro->rubro?->descripcion,
            'presupuestado' => (float) $this->obraRubro->presupuestado,
            'acumulado' => (float) $this->obraRubro->acumulado,
            'monto_intentado' => $this->montoIntentado,
            'entrada_type' => $this->entrada::class,
            'entrada_id' => $this->entrada->getKey(),
            'entrada_folio' => $this->entrada->folio ?? null,
            'mensaje' => $this->mensaje(),
        ];
    }

    private function mensaje(): string
    {
        $rubro = $this->obraRubro->rubro?->descripcion ?? '(rubro)';
        $obra = $this->obraRubro->obra?->descripcion ?? '(obra)';
        $folio = $this->entrada->folio ?? '?';

        if ($this->nivel === 'sobregiro') {
            return sprintf(
                'Sobregiro presupuestal en "%s · %s" causado por %s.',
                $obra,
                $rubro,
                $folio,
            );
        }

        return sprintf(
            'El rubro "%s · %s" entró en zona crítica tras %s.',
            $obra,
            $rubro,
            $folio,
        );
    }
}
