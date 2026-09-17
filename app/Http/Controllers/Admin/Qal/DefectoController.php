<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\DefectoRequest;
use App\Models\Qal\Defecto;
use Illuminate\Http\RedirectResponse;

/**
 * El catálogo de defectos, de todas las etapas.
 *
 * El ámbito se elige al dar de alta y no cambia: mover un defecto de soldadura
 * a pintura cambiaría el significado de todo lo que ya se capturó con él.
 *
 * Como los demás catálogos, no tiene baja: se desactiva.
 */
class DefectoController extends Controller
{
    public function store(DefectoRequest $request): RedirectResponse
    {
        Defecto::create($request->validated() + ['activo' => true]);

        return back();
    }

    public function update(DefectoRequest $request, int $id): RedirectResponse
    {
        Defecto::findOrFail($id)->update($request->validated());

        return back();
    }

    public function toggle(int $id): RedirectResponse
    {
        $defecto = Defecto::findOrFail($id);
        $defecto->update(['activo' => ! $defecto->activo]);

        return back();
    }
}
