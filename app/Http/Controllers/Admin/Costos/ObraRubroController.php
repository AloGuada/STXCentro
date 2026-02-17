<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Models\Costos\ObraRubro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ObraRubroController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'obra_id' => ['required', 'exists:obras,id'],
            'rubro_id' => ['required', 'exists:costos_rubros,id'],
            'presupuestado' => ['required', 'numeric', 'min:0'],
        ]);

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
