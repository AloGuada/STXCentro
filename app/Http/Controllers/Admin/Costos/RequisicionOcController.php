<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\RequisicionEstatus;
use App\Http\Controllers\Controller;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionOc;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Metadatos de las OCs planeadas de una requisición (modo de pago, fecha de
 * entrega, notas). Se editan en el tab "Definir OC" antes de aprobar. Las
 * líneas/cantidades viven en las selecciones; aquí solo lo que el aprobador
 * valida. Editable mientras la requisición no esté liberada/cancelada.
 */
class RequisicionOcController extends Controller
{
    public function store(Request $request, Requisicion $requisicion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        $this->ensureEditable($requisicion->estatus);

        $validated = $request->validate([
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'numero_oc' => ['required', 'integer', 'min:1', 'max:50'],
            'modo_pago' => ['required', 'in:contado,credito'],
            'metodo_pago' => ['required', 'in:transferencia,cheque,efectivo'],
            'fecha_entrega' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'pagos' => ['nullable', 'array', 'max:12'],
            'pagos.*.porcentaje' => ['required_with:pagos', 'numeric', 'min:0.01', 'max:100'],
            'pagos.*.concepto' => ['nullable', 'string', 'max:120'],
        ]);

        // Las parcialidades solo aplican a contado y su % debe sumar 100.
        $pagos = $validated['modo_pago'] === 'contado' ? ($validated['pagos'] ?? []) : [];

        if (count($pagos) > 0) {
            $suma = array_sum(array_map(fn ($p) => (float) $p['porcentaje'], $pagos));
            if (abs($suma - 100.0) > 0.01) {
                return back()->withErrors([
                    'pagos' => sprintf('Los porcentajes de los pagos deben sumar 100%% (suman %.2f%%).', $suma),
                ]);
            }
        }

        RequisicionOc::updateOrCreate(
            [
                'requisicion_id' => $requisicion->id,
                'proveedor_id' => $validated['proveedor_id'],
                'numero_oc' => $validated['numero_oc'],
            ],
            [
                'modo_pago' => $validated['modo_pago'],
                'metodo_pago' => $validated['metodo_pago'],
                'fecha_entrega' => $validated['fecha_entrega'] ?? null,
                'notas' => $validated['notas'] ?? null,
                'pagos' => count($pagos) > 0 ? array_values($pagos) : null,
            ],
        );

        return back()->with('success', 'OC actualizada.');
    }

    private function ensureEditable(RequisicionEstatus $estatus): void
    {
        if (in_array($estatus, [RequisicionEstatus::Liberada, RequisicionEstatus::Cancelada], true)) {
            abort(422, 'La requisición ya no permite editar las OCs.');
        }
    }
}
