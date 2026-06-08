<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\RequisicionEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\RequisicionSeleccionStoreRequest;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionSeleccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Maneja la distribucion final de cantidad por proveedor para cada partida
 * de la requisicion. Permite split: una partida puede repartirse entre
 * varios proveedores, siempre que la suma de cantidades no exceda la
 * cantidad solicitada en la partida.
 */
class RequisicionSeleccionController extends Controller
{
    public function store(RequisicionSeleccionStoreRequest $request): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        $cotizacion = RequisicionCotizacionPrecio::with('detalle.requisicion', 'detalle.selecciones')
            ->findOrFail($request->integer('cotizacion_precio_id'));

        $this->ensureEditable($cotizacion->detalle->requisicion->estatus);

        $cantidad = (float) $request->input('cantidad');
        $numeroOc = (int) ($request->input('numero_oc') ?: 1);
        $cantidadPartida = (float) $cotizacion->detalle->cantidad;
        $sumaPrevia = (float) $cotizacion->detalle->selecciones->sum('cantidad');

        if ($sumaPrevia + $cantidad > $cantidadPartida + config('costos.epsilon_cantidad')) {
            return back()->withErrors([
                'cantidad' => sprintf(
                    'La suma de selecciones (%.2f) excede la cantidad solicitada de la partida (%.2f).',
                    $sumaPrevia + $cantidad,
                    $cantidadPartida,
                ),
            ]);
        }

        // Consolidacion por (partida, precio cotizado, numero_oc): el mismo
        // proveedor en distintas OCs (numero_oc != ) produce filas separadas
        // para que liberar() agrupe correctamente y compras pueda partir
        // la compra del mismo proveedor en varias OCs.
        $existente = RequisicionSeleccion::where('requisicion_detalle_id', $cotizacion->requisicion_detalle_id)
            ->where('cotizacion_precio_id', $cotizacion->id)
            ->where('numero_oc', $numeroOc)
            ->first();

        if ($existente) {
            $existente->increment('cantidad', $cantidad);

            return back()->with('success', 'Cantidad sumada a la selección existente.');
        }

        RequisicionSeleccion::create([
            'requisicion_detalle_id' => $cotizacion->requisicion_detalle_id,
            'cotizacion_precio_id' => $cotizacion->id,
            'numero_oc' => $numeroOc,
            'proveedor_id' => $cotizacion->proveedor_id,
            'cantidad' => $cantidad,
        ]);

        return back()->with('success', 'Selección guardada.');
    }

    public function destroy(RequisicionSeleccion $seleccion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        $seleccion->load('detalle.requisicion');
        $this->ensureEditable($seleccion->detalle->requisicion->estatus);

        $seleccion->delete();

        return back()->with('success', 'Selección eliminada.');
    }

    private function ensureEditable(RequisicionEstatus $estatus): void
    {
        if (! in_array($estatus, [
            RequisicionEstatus::Cotizada,
            RequisicionEstatus::Borrador,
            RequisicionEstatus::Rechazada,
        ], true)) {
            abort(422, 'La requisición ya no permite editar selecciones.');
        }
    }
}
