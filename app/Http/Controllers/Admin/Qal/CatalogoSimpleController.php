<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Lo que comparten los catálogos de Calidad de un solo campo.
 *
 * Seis listas —equipos, operadores, responsables, supervisores de pintura y los
 * dos de defectos— son exactamente la misma pantalla con otro nombre. Se
 * escriben una vez aquí y cada una aporta su modelo y su etiqueta.
 *
 * No hay `destroy` a propósito: en estos catálogos nada se borra, se desactiva.
 * Sacar un valor de los desplegables no debe tocar los registros que ya lo
 * mencionan, y borrarlo dejaría reportes citando algo que ya no existe.
 */
abstract class CatalogoSimpleController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function modelo(): string;

    /** Cómo se nombra un elemento en los mensajes de error. */
    abstract protected function etiqueta(): string;

    public function store(Request $request): RedirectResponse
    {
        $modelo = $this->modelo();

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique((new $modelo)->getTable(), 'nombre')],
        ], [], ['nombre' => $this->etiqueta()]);

        $modelo::create($datos + ['activo' => true]);

        return back();
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $modelo = $this->modelo();
        $fila = $modelo::findOrFail($id);

        $datos = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique((new $modelo)->getTable(), 'nombre')->ignore($fila->getKey()),
            ],
        ], [], ['nombre' => $this->etiqueta()]);

        $fila->update($datos);

        return back();
    }

    /**
     * Activa o desactiva. Es la única baja que existe aquí.
     */
    public function toggle(int $id): RedirectResponse
    {
        $modelo = $this->modelo();
        $fila = $modelo::findOrFail($id);

        $fila->update(['activo' => ! $fila->activo]);

        return back();
    }
}
