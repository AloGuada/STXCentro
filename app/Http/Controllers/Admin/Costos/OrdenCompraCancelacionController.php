<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\SolicitudPagoEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\CancelarUnidadesRequest;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\OrdenCompraDetalleCancelacion;
use App\Models\Costos\SolicitudPago;
use App\Services\Costos\CanceladorDeUnidades;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Cancelar unidades de una partida de una orden ya emitida.
 *
 * Compras solicita con el mismo permiso con el que cancela órdenes; el jefe de
 * compras autoriza o rechaza con `costos.ordenes-compra.autorizar-cancelacion`.
 * Mientras la cancelación esté pendiente, la orden se reporta como pendiente de
 * aprobación.
 */
class OrdenCompraCancelacionController extends Controller
{
    public function __construct(private readonly CanceladorDeUnidades $cancelador) {}

    public function store(CancelarUnidadesRequest $request, OrdenCompraDetalle $detalle): RedirectResponse
    {
        Gate::authorize('costos.ordenes-compra.cancelar');

        $this->cancelador->solicitar(
            $detalle,
            (float) $request->input('cantidad'),
            (string) $request->string('motivo'),
            $request->user()?->getKey(),
        );

        return back()->with('success', 'Cancelación registrada: queda pendiente de que la autorice el jefe de compras.');
    }

    public function autorizar(OrdenCompraDetalleCancelacion $cancelacion): RedirectResponse
    {
        Gate::authorize('costos.ordenes-compra.autorizar-cancelacion');

        $ajustadas = $this->cancelador->autorizar($cancelacion, auth()->id());

        $mensaje = 'Cancelación autorizada: las unidades salieron de la orden.';

        if ($ajustadas->isNotEmpty()) {
            $mensaje .= ' Se ajustó la solicitud de pago: '.$ajustadas
                ->map(fn (SolicitudPago $s): string => $s->estatus === SolicitudPagoEstatus::Cancelada
                    ? "{$s->folio} (cancelada)"
                    : sprintf('%s ($%s)', $s->folio, number_format((float) $s->monto_total, 2)))
                ->join(', ').'.';
        }

        return back()->with('success', $mensaje);
    }

    public function rechazar(Request $request, OrdenCompraDetalleCancelacion $cancelacion): RedirectResponse
    {
        Gate::authorize('costos.ordenes-compra.autorizar-cancelacion');

        $validado = $request->validate([
            'motivo_rechazo' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'motivo_rechazo.required' => 'Explique por qué no se autoriza la cancelación.',
            'motivo_rechazo.min' => 'El motivo del rechazo debe explicar la decisión (al menos 10 caracteres).',
        ]);

        $this->cancelador->rechazar($cancelacion, $validado['motivo_rechazo'], auth()->id());

        return back()->with('success', 'Cancelación rechazada: la partida queda como estaba.');
    }
}
