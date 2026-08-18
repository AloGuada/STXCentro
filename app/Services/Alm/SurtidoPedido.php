<?php

namespace App\Services\Alm;

use App\Enums\Alm\PedidoEstatus;
use App\Models\Alm\Pedido;
use App\Models\Alm\PedidoDetalle;
use Illuminate\Support\Facades\DB;

/**
 * Mantiene `cantidad_surtida` y el estatus del pedido.
 *
 * **Recalcula, nunca incrementa.** Sumarle de a poco obligaría a escribir la
 * resta al cancelar una salida, y esa resta se equivoca en cuanto aparece un
 * caso nuevo. Con un `SUM` sobre los documentos vivos, la columna es
 * auto-sanable: correr el recálculo dos veces da lo mismo que correrlo una.
 */
class SurtidoPedido
{
    /**
     * Recalcula todos los renglones del pedido y mueve el estatus.
     *
     * Corre dentro de la transacción del llamador —salida y transferencia lo
     * invocan después de grabar sus renglones— para que el pedido nunca quede
     * visible como surtible cuando el material ya salió.
     */
    public function recalcular(Pedido $pedido): PedidoEstatus
    {
        return DB::transaction(function () use ($pedido): PedidoEstatus {
            $surtido = $this->surtidoPorRenglon($pedido);

            foreach ($pedido->detalles as $detalle) {
                $detalle->update(['cantidad_surtida' => $surtido[$detalle->id] ?? 0]);
            }

            $pedido->refresh();

            return $this->moverEstatus($pedido);
        });
    }

    /** Lo que falta por entregar de un renglón, ya descontando lo que va en camino. */
    public function pendiente(PedidoDetalle $detalle): float
    {
        return $detalle->pendiente();
    }

    /**
     * Cuánto se ha entregado de cada renglón, sumando los dos documentos que lo
     * pueden surtir.
     *
     * En una transferencia cuenta **lo enviado**, no lo confirmado: el almacén
     * origen ya cumplió y no tiene el material para volver a surtirlo. Si contara
     * lo confirmado, un pedido con transferencia en tránsito seguiría apareciendo
     * como surtible y el almacenista lo despacharía dos veces — el peor bug
     * posible del módulo. El faltante del traslado tiene su propio dueño y no es
     * deuda del pedido; si la obra necesita reponerlo, levanta uno nuevo y así la
     * pérdida queda visible.
     *
     * @return array<int, float>
     */
    private function surtidoPorRenglon(Pedido $pedido): array
    {
        $renglones = $pedido->detalles->pluck('id');

        if ($renglones->isEmpty()) {
            return [];
        }

        $porSalida = DB::table('alm_salida_detalle')
            ->join('alm_salidas', 'alm_salidas.id', '=', 'alm_salida_detalle.salida_id')
            ->whereIn('alm_salida_detalle.pedido_detalle_id', $renglones)
            ->whereNull('alm_salidas.cancelada_at')
            ->groupBy('alm_salida_detalle.pedido_detalle_id')
            ->select('alm_salida_detalle.pedido_detalle_id')
            ->selectRaw('SUM(alm_salida_detalle.cantidad) as entregado')
            ->get()
            ->keyBy('pedido_detalle_id');

        // La transferencia todavía no existe como documento; cuando se
        // construya, su rama suma aquí `cantidad_enviada` de las no canceladas.
        $porTransferencia = collect();

        $total = [];

        foreach ($renglones as $id) {
            $total[$id] = (float) ($porSalida[$id]->entregado ?? 0)
                + (float) ($porTransferencia[$id]->entregado ?? 0);
        }

        return $total;
    }

    /**
     * Pasa a `surtido` cuando **todos** los renglones alcanzaron lo solicitado, y
     * regresa a `aprobado` si una cancelación destapó un faltante.
     *
     * Sólo se mueve entre esos dos: un pedido cancelado o rechazado no revive
     * porque alguien capturó una salida contra él.
     */
    private function moverEstatus(Pedido $pedido): PedidoEstatus
    {
        if (! in_array($pedido->estatus, [PedidoEstatus::Aprobado, PedidoEstatus::Surtido], true)) {
            return $pedido->estatus;
        }

        $epsilon = (float) config('costos.epsilon_cantidad');

        $completo = $pedido->detalles->every(
            fn (PedidoDetalle $d): bool => (float) $d->cantidad_surtida >= (float) $d->cantidad_solicitada - $epsilon,
        );

        $nuevo = $completo ? PedidoEstatus::Surtido : PedidoEstatus::Aprobado;

        if ($nuevo !== $pedido->estatus) {
            $pedido->update(['estatus' => $nuevo]);
        }

        return $nuevo;
    }
}
