<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Models\Obra;
use App\Models\Prod\Proceso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Qué procesos se pagan como destajo en una obra.
 */
class ObraProcesoController extends Controller
{
    public function update(Request $request, Obra $obra): RedirectResponse
    {
        $datos = $request->validate([
            'procesos' => ['present', 'array'],
            'procesos.*' => ['integer', 'exists:prod_procesos,id'],
        ], [
            'procesos.present' => 'Indica qué procesos paga la obra.',
        ]);

        $procesos = collect($datos['procesos'])->map(fn ($id) => (int) $id);

        // Quitar un proceso que ya tiene producción capturada dejaría registros
        // y tarifas colgando de algo que la obra dice no pagar.
        $enUso = Proceso::query()
            ->whereNotIn('id', $procesos)
            ->whereHas('registros.pieza.catalogo', fn ($q) => $q->where('obra_id', $obra->id))
            ->pluck('nombre');

        if ($enUso->isNotEmpty()) {
            return back()->withErrors([
                'procesos' => "No se puede quitar {$enUso->implode(', ')}: la obra ya tiene producción capturada en ese proceso.",
            ]);
        }

        $obra->procesos()->sync($procesos);

        return back()->with('success', 'Procesos de la obra actualizados.');
    }
}
