<?php

namespace App\Listeners\Costos;

use App\Events\Costos\PresupuestoExcedido;
use App\Models\Costos\AprobacionDepartamento;
use App\Models\Usuario;
use App\Notifications\Costos\PresupuestoExcedidoNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Cuando se dispara PresupuestoExcedido, identifica el departamento
 * involucrado (vía la entrada: OC.departamento_id o SolicitudPago.departamento_id),
 * resuelve los aprobadores configurados en `costos_aprobacion_departamento`
 * y les manda PresupuestoExcedidoNotification por canal database.
 *
 * Si no se puede determinar el departamento, no envía nada (silencioso).
 */
class NotificarAprobadoresPresupuesto
{
    public function handle(PresupuestoExcedido $event): void
    {
        $departamentoId = $event->entrada->departamento_id ?? null;

        if (! $departamentoId) {
            return;
        }

        $aprobadorIds = AprobacionDepartamento::where('departamento_id', $departamentoId)
            ->whereNotNull('aprobador_id')
            ->pluck('aprobador_id')
            ->unique()
            ->all();

        if (empty($aprobadorIds)) {
            return;
        }

        $usuarios = Usuario::whereIn('id', $aprobadorIds)->get();

        Notification::send(
            $usuarios,
            new PresupuestoExcedidoNotification(
                $event->obraRubro,
                $event->montoIntentado,
                $event->entrada,
                $event->nivel,
            ),
        );
    }
}
