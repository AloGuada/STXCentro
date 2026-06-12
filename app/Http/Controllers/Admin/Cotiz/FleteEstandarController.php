<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Enums\Cotiz\MetodoFleteEstandar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\ObraFleteEstandarStoreRequest;
use App\Http\Requests\Admin\Cotiz\ObraFleteEstandarUpdateRequest;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraFleteEstandar;
use Illuminate\Http\RedirectResponse;

/**
 * Análisis de Fletes Estándar de una obra (Fase 4): tarjetas con sus parámetros de
 * camionaje. El volumen y los camiones se derivan en PHP (FleteEstandarDerivations).
 */
class FleteEstandarController extends Controller
{
    public function store(ObraFleteEstandarStoreRequest $request, Obra $obra): RedirectResponse
    {
        $grupo = $request->input('grupo');
        $maxOrden = (int) $obra->fletesEstandar()->max('orden');

        $obra->fletesEstandar()->create([
            'tarjeta_id' => $request->integer('tarjeta_id'),
            'metodo' => $request->input('metodo', MetodoFleteEstandar::PorKg->value),
            'grupo' => ($grupo === '' ? null : $grupo),
            'orden' => $maxOrden + 1,
            'kg_por_camion' => 15000,
            'pzas_por_camion' => 400,
            'ml_por_pza' => 3.05,
        ]);

        return back();
    }

    public function update(ObraFleteEstandarUpdateRequest $request, ObraFleteEstandar $fleteEstandar): RedirectResponse
    {
        $data = $request->validated();

        if (array_key_exists('grupo', $data) && $data['grupo'] === '') {
            $data['grupo'] = null;
        }

        $fleteEstandar->update($data);

        return back();
    }

    public function destroy(ObraFleteEstandar $fleteEstandar): RedirectResponse
    {
        $fleteEstandar->delete();

        return back();
    }
}
