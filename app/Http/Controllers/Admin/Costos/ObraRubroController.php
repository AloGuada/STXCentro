<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\Rubro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ObraRubroController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'presupuesto_id' => ['required', 'exists:costos_presupuestos,id'],
            'rubro_id' => ['required', 'exists:costos_rubros,id'],
            'presupuestado' => ['required', 'numeric', 'min:0'],
        ]);

        $presupuesto = Presupuesto::findOrFail($validated['presupuesto_id']);
        $rubro = Rubro::findOrFail($validated['rubro_id']);

        if ($rubro->ambito !== $presupuesto->ambitoRubros()) {
            return back()->withErrors([
                'rubro_id' => 'El centro de costos no corresponde al ámbito de este presupuesto.',
            ]);
        }

        $presupuesto->crearRubro((int) $rubro->id, (float) $validated['presupuestado']);

        return back();
    }

    public function storeAll(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'presupuesto_id' => ['required', 'exists:costos_presupuestos,id'],
        ]);

        Presupuesto::findOrFail($validated['presupuesto_id'])->sembrarRubrosFaltantes();

        return back();
    }

    public function update(Request $request, ObraRubro $obraRubro): RedirectResponse
    {
        $validated = $request->validate([
            'presupuestado' => ['required', 'numeric', 'min:0'],
            'acumulado' => ['required', 'numeric', 'min:0'],
        ]);

        $obraRubro->update($validated);

        return back();
    }

    public function destroy(ObraRubro $obraRubro): RedirectResponse
    {
        $obraRubro->delete();

        return back();
    }
}
