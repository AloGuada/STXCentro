<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\ObraCuadrillaGlobalUpsertRequest;
use App\Http\Requests\Admin\Cotiz\ObraNumGruposRequest;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraCuadrillaGlobal;
use Illuminate\Http\RedirectResponse;

/**
 * Cuadrilla Global de Montaje de una obra (Fase 4, pestaña "Montaje Global"):
 * captura de personas por categoría/grupo + el número de grupos de la obra.
 */
class CuadrillaGlobalController extends Controller
{
    public function upsert(ObraCuadrillaGlobalUpsertRequest $request, Obra $obra): RedirectResponse
    {
        $data = $request->validated();

        ObraCuadrillaGlobal::query()->updateOrCreate(
            ['obra_id' => $obra->id, 'categoria_id' => $data['categoria_id']],
            ['cantidad_por_grupo' => $data['cantidad_por_grupo']],
        );

        return back();
    }

    public function updateGrupos(ObraNumGruposRequest $request, Obra $obra): RedirectResponse
    {
        $obra->update(['num_grupos' => $request->integer('num_grupos')]);

        return back();
    }
}
