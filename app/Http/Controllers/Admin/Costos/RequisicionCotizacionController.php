<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\RequisicionEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\RequisicionCotizacionPrecioStoreRequest;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Maneja la matriz de precio comparativo de la requisicion: por cada
 * (partida, proveedor) hay un solo registro de cotizacion. Solo accesible
 * mientras la requisicion este en `borrador` o `cotizada` (compras todavia
 * editando) o `rechazada` (re-cotizando).
 */
class RequisicionCotizacionController extends Controller
{
    public function store(RequisicionCotizacionPrecioStoreRequest $request): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        $detalle = RequisicionDetalle::with('requisicion')->findOrFail(
            $request->integer('requisicion_detalle_id')
        );

        $this->ensureEditable($detalle->requisicion->estatus);

        RequisicionCotizacionPrecio::updateOrCreate(
            [
                'requisicion_detalle_id' => $detalle->id,
                'proveedor_id' => $request->integer('proveedor_id'),
            ],
            [
                'precio_unitario' => $request->float('precio_unitario'),
                'moneda' => $request->input('moneda'),
                'tiempo_entrega_dias' => $request->input('tiempo_entrega_dias'),
                'observaciones' => $request->input('observaciones'),
            ],
        );

        $this->promoverACotizada($detalle->requisicion);

        return back()->with('success', 'Precio cotizado guardado.');
    }

    public function destroy(RequisicionCotizacionPrecio $precio): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        $precio->load('detalle.requisicion');
        $this->ensureEditable($precio->detalle->requisicion->estatus);

        // Si el precio se uso en alguna seleccion, quitar la seleccion antes.
        $precio->detalle->selecciones()
            ->where('cotizacion_precio_id', $precio->id)
            ->delete();

        $precio->delete();

        return back()->with('success', 'Precio eliminado.');
    }

    private function ensureEditable(RequisicionEstatus $estatus): void
    {
        if (! in_array($estatus, [
            RequisicionEstatus::Borrador,
            RequisicionEstatus::Cotizada,
            RequisicionEstatus::Rechazada,
        ], true)) {
            abort(422, 'La requisición ya no permite editar cotizaciones.');
        }
    }

    /**
     * Si la requisicion estaba en borrador y ya tiene al menos una cotizacion,
     * promueve a cotizada. Idempotente.
     */
    private function promoverACotizada(\App\Models\Costos\Requisicion $requisicion): void
    {
        if ($requisicion->estatus === RequisicionEstatus::Borrador) {
            $requisicion->transitionTo(RequisicionEstatus::Cotizada);
        }
    }
}
