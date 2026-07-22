<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Models\Costos\ConfiguracionCostos;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pantalla de configuración (fila única) del módulo de Costos: plazos de
 * apartado de presupuesto y de cancelación automática de requisiciones y
 * solicitudes de pago no aprobadas.
 */
class ConfiguracionCostosController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/costos/configuracion/edit', [
            'configuracion' => ConfiguracionCostos::actual(),
            'usuarios' => Usuario::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'dias_apartado' => ['required', 'integer', 'min:1', 'max:365'],
            'dias_cancelar_requisicion' => ['required', 'integer', 'min:1', 'max:365'],
            'dias_cancelar_solicitud' => ['required', 'integer', 'min:1', 'max:365'],
            'corte_activo' => ['required', 'boolean'],
            'corte_dia' => ['required', 'integer', 'min:1', 'max:5'],
            'corte_hora' => ['required', 'date_format:H:i'],
            'dia_comprobante_recepcion' => ['nullable', 'integer', 'min:0', 'max:6'],
            'gerente_compras_id' => ['nullable', 'string', 'exists:usuarios,id'],
        ]);

        ConfiguracionCostos::actual()->update($validated);

        return back()->with('success', 'Configuración de costos actualizada.');
    }
}
