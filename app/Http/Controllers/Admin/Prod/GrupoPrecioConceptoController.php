<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Models\Prod\GrupoPrecioConcepto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GrupoPrecioConceptoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'concepto_id' => ['required', 'exists:conceptos,id'],
            'grupo_precio_id' => ['required', 'exists:prod_grupos_precio,id'],
        ]);

        GrupoPrecioConcepto::firstOrCreate([
            'concepto_id' => $request->concepto_id,
            'grupo_precio_id' => $request->grupo_precio_id,
        ]);

        return back();
    }

    public function destroy(GrupoPrecioConcepto $grupoPrecioConcepto): RedirectResponse
    {
        $grupoPrecioConcepto->delete();

        return back();
    }
}
