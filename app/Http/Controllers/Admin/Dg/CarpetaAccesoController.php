<?php

namespace App\Http\Controllers\Admin\Dg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dg\CarpetaAccesoRequest;
use App\Models\Dg\Carpeta;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CarpetaAccesoController extends Controller
{
    public function index(Carpeta $carpeta): Response
    {
        $this->authorize('gestionarAccesos', $carpeta);

        $carpeta->load(['usuarios' => fn ($q) => $q->orderBy('name')]);

        $idsAsignados = $carpeta->usuarios->pluck('id')->all();
        $disponibles = Usuario::query()
            ->whereNotIn('id', $idsAsignados)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('admin/dg/carpetas/accesos', [
            'carpeta' => $carpeta->only(['id', 'nombre', 'descripcion']),
            'asignados' => $carpeta->usuarios->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'puede_escribir' => (bool) $u->pivot->puede_escribir,
            ]),
            'disponibles' => $disponibles,
        ]);
    }

    public function store(CarpetaAccesoRequest $request, Carpeta $carpeta): RedirectResponse
    {
        $this->authorize('gestionarAccesos', $carpeta);

        $carpeta->usuarios()->syncWithoutDetaching([
            $request->string('usuario_id')->toString() => [
                'puede_escribir' => $request->boolean('puede_escribir'),
            ],
        ]);

        return back()->with('success', 'Acceso asignado.');
    }

    public function update(Carpeta $carpeta, Usuario $usuario): RedirectResponse
    {
        $this->authorize('gestionarAccesos', $carpeta);

        $actual = $carpeta->usuarios()->where('usuario_id', $usuario->getKey())->first();
        $nuevoValor = $actual ? ! (bool) $actual->pivot->puede_escribir : true;

        $carpeta->usuarios()->updateExistingPivot($usuario->getKey(), [
            'puede_escribir' => $nuevoValor,
        ]);

        return back()->with('success', 'Permiso actualizado.');
    }

    public function destroy(Carpeta $carpeta, Usuario $usuario): RedirectResponse
    {
        $this->authorize('gestionarAccesos', $carpeta);

        $carpeta->usuarios()->detach($usuario->getKey());

        return back()->with('success', 'Acceso removido.');
    }
}
