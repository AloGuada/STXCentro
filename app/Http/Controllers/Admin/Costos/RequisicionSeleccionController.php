<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\RequisicionEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\RequisicionSeleccionStoreRequest;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionOc;
use App\Models\Costos\RequisicionSeleccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        if ($cotizacion->detalle->solo_cotizacion) {
            return back()->withErrors([
                'cotizacion_precio_id' => 'La partida es solo cotización: se compara como referencia pero no se surte en la OC.',
            ]);
        }

        // El comparativo dibuja las celdas por opción: sin ella la cotización no
        // tiene columna y la selección quedaría apuntando a una fila invisible
        // (la celda que sí se ve nunca se marcaría como seleccionada).
        if ($cotizacion->opcion_id === null) {
            return back()->withErrors([
                'cotizacion_precio_id' => 'Esa cotización no está ligada a una columna del comparativo. Vuelve a capturar el precio en la columna del proveedor.',
            ]);
        }

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
            $this->asegurarMetadatosOc($cotizacion, $numeroOc);

            return back()->with('success', 'Cantidad sumada a la selección existente.');
        }

        RequisicionSeleccion::create([
            'requisicion_detalle_id' => $cotizacion->requisicion_detalle_id,
            'cotizacion_precio_id' => $cotizacion->id,
            'numero_oc' => $numeroOc,
            'proveedor_id' => $cotizacion->proveedor_id,
            'cantidad' => $cantidad,
        ]);

        $this->asegurarMetadatosOc($cotizacion, $numeroOc);

        return back()->with('success', 'Selección guardada.');
    }

    /**
     * Ajusta la cantidad de una selección existente (edición inline en el tab
     * de OC). Valida que la suma de selecciones de la partida no exceda lo
     * solicitado. Si la cantidad baja a 0, elimina la selección.
     */
    public function update(Request $request, RequisicionSeleccion $seleccion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        $seleccion->load('detalle.requisicion', 'detalle.selecciones');
        $this->ensureEditable($seleccion->detalle->requisicion->estatus);

        $cantidad = (float) $request->input('cantidad');

        if ($cantidad <= 0) {
            $seleccion->delete();

            return back()->with('success', 'Selección eliminada.');
        }

        $cantidadPartida = (float) $seleccion->detalle->cantidad;
        $sumaOtras = (float) $seleccion->detalle->selecciones
            ->where('id', '!=', $seleccion->id)
            ->sum('cantidad');

        if ($sumaOtras + $cantidad > $cantidadPartida + config('costos.epsilon_cantidad')) {
            return back()->withErrors([
                'cantidad' => sprintf(
                    'La suma de selecciones (%.2f) excede la cantidad solicitada de la partida (%.2f).',
                    $sumaOtras + $cantidad,
                    $cantidadPartida,
                ),
            ]);
        }

        $seleccion->update(['cantidad' => $cantidad]);

        return back()->with('success', 'Cantidad actualizada.');
    }

    /**
     * Crea la fila de metadatos de la OC (modo de pago, fecha, notas) si aún no
     * existe, para el grupo (requisicion, proveedor, numero_oc). El default de
     * modo de pago respeta si el proveedor maneja crédito; la fecha se siembra
     * a partir del tiempo de entrega cotizado (o 7 días).
     */
    private function asegurarMetadatosOc(RequisicionCotizacionPrecio $cotizacion, int $numeroOc): void
    {
        $requisicionId = $cotizacion->detalle->requisicion->id;
        $proveedor = $cotizacion->proveedor()->first();
        $dias = (int) ($cotizacion->tiempo_entrega_dias ?: 7);

        RequisicionOc::firstOrCreate(
            [
                'requisicion_id' => $requisicionId,
                'proveedor_id' => $cotizacion->proveedor_id,
                'numero_oc' => $numeroOc,
            ],
            [
                'modo_pago' => $proveedor?->maneja_credito ? 'credito' : 'contado',
                'fecha_entrega' => now()->addDays($dias)->format('Y-m-d'),
                'notas' => null,
            ],
        );
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
            RequisicionEstatus::Borrador,
            RequisicionEstatus::Rechazada,
        ], true)) {
            abort(422, 'La requisición ya no permite editar selecciones.');
        }
    }
}
