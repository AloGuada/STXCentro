<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\RequisicionEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\RequisicionCotizacionPrecioStoreRequest;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Proveedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $valores = ['precio_unitario' => $request->float('precio_unitario')];

        foreach (['codigo_producto', 'moneda', 'tiempo_entrega_dias', 'observaciones'] as $campo) {
            if ($request->has($campo)) {
                $valores[$campo] = $request->input($campo);
            }
        }

        RequisicionCotizacionPrecio::updateOrCreate(
            [
                'requisicion_detalle_id' => $detalle->id,
                'proveedor_id' => $request->integer('proveedor_id'),
            ],
            $valores,
        );

        $this->promoverACotizada($detalle->requisicion);

        return back()->with('success', 'Precio cotizado guardado.');
    }

    /**
     * Clasificación fiscal de la partida (tipo_fiscal), que Compras captura en
     * el tab de cotización. El código de producto NO va aquí: es por línea y
     * por proveedor, se guarda en cada cotización.
     */
    public function clasificar(Request $request, RequisicionDetalle $detalle): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        $detalle->load('requisicion');
        $this->ensureEditable($detalle->requisicion->estatus);

        $validated = $request->validate([
            'tipo_fiscal' => ['required', 'in:mercancia,flete,servicio_profesional,renta'],
        ]);

        $detalle->update(['tipo_fiscal' => $validated['tipo_fiscal']]);

        return back()->with('success', 'Clasificación de la partida actualizada.');
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

    /**
     * Quita un proveedor completo de la matriz de cotización: borra todas sus
     * cotizaciones en la requisición y las selecciones que las usaban. Usado
     * por el botón de quitar columna en el tab de cotización.
     */
    public function destroyProveedor(Requisicion $requisicion, Proveedor $proveedor): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        $this->ensureEditable($requisicion->estatus);

        $detalleIds = $requisicion->detalles()->pluck('id');

        RequisicionSeleccion::whereIn('requisicion_detalle_id', $detalleIds)
            ->where('proveedor_id', $proveedor->id)
            ->delete();

        RequisicionCotizacionPrecio::whereIn('requisicion_detalle_id', $detalleIds)
            ->where('proveedor_id', $proveedor->id)
            ->delete();

        return back()->with('success', 'Proveedor eliminado de la cotización.');
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
