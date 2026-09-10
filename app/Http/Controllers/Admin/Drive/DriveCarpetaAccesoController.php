<?php

namespace App\Http\Controllers\Admin\Drive;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Drive\CarpetaAccesoRequest;
use App\Models\Drive\Carpeta;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;

class DriveCarpetaAccesoController extends Controller
{
    public function store(CarpetaAccesoRequest $request, Carpeta $carpeta): RedirectResponse
    {
        $this->authorize('gestionarAccesos', $carpeta);

        $usuarioId = $request->string('usuario_id')->toString();

        abort_if($usuarioId === $carpeta->usuario_id, 422, 'El creador de la carpeta ya tiene acceso.');

        $carpeta->usuarios()->syncWithoutDetaching([
            $usuarioId => ['puede_escribir' => $request->boolean('puede_escribir')],
        ]);

        return back()->with('success', 'Acceso asignado correctamente.');
    }

    public function update(Carpeta $carpeta, Usuario $usuario): RedirectResponse
    {
        $this->authorize('gestionarAccesos', $carpeta);

        $actual = $carpeta->usuarios()->where('usuario_id', $usuario->getKey())->first();

        abort_if($actual === null, 404);

        $carpeta->usuarios()->updateExistingPivot($usuario->getKey(), [
            'puede_escribir' => ! (bool) $actual->pivot->puede_escribir,
        ]);

        return back()->with('success', 'Permiso actualizado correctamente.');
    }

    public function destroy(Carpeta $carpeta, Usuario $usuario): RedirectResponse
    {
        $this->authorize('gestionarAccesos', $carpeta);

        $carpeta->usuarios()->detach($usuario->getKey());

        return back()->with('success', 'Acceso removido correctamente.');
    }
}
