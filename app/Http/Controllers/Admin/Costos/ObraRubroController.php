<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Rubro;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ObraRubroController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'obra_id' => ['required', 'exists:obras,id'],
            'adicional_partida_id' => ['nullable', 'exists:cob_partidas,id'],
            'rubro_id' => ['required', 'exists:costos_rubros,id'],
            'presupuestado' => ['required', 'numeric', 'min:0'],
        ]);

        $obra = Obra::findOrFail($validated['obra_id']);
        $rubro = Rubro::findOrFail($validated['rubro_id']);

        if ($obra->es_planta !== ($rubro->ambito === 'planta')) {
            return back()->withErrors([
                'rubro_id' => 'El centro de costos no corresponde al ámbito de esta obra/planta.',
            ]);
        }

        ObraRubro::create($validated);

        return back();
    }

    public function update(Request $request, ObraRubro $obraRubro): RedirectResponse
    {
        $validated = $request->validate([
            'presupuestado' => ['required', 'numeric', 'min:0'],
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
