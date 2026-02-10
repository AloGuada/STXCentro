<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Models\Prod\MarcaGrupo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MarcaGrupoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'pieza_id' => ['required', 'exists:piezas,id'],
            'grupo_precio_id' => ['required', 'exists:prod_grupo_precios,id'],
        ]);

        MarcaGrupo::firstOrCreate([
            'pieza_id' => $request->pieza_id,
            'grupo_precio_id' => $request->grupo_precio_id,
        ]);

        return back();
    }

    public function destroy(MarcaGrupo $marcaGrupo): RedirectResponse
    {
        $marcaGrupo->delete();

        return back();
    }
}
