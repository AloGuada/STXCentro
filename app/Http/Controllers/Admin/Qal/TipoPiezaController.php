<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Models\Qal\TipoPieza;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tipos de pieza, por el prefijo oficial de ingeniería.
 *
 * El prefijo es lo que hace que el tipo se deduzca solo de la marca, así que se
 * guarda siempre en mayúsculas y sin espacios: `tp` y `TP ` son el mismo
 * prefijo, y si entran los dos la deducción se vuelve impredecible.
 */
class TipoPiezaController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        TipoPieza::create($this->validar($request) + ['activo' => true]);

        return back();
    }

    public function update(Request $request, TipoPieza $tipoPieza): RedirectResponse
    {
        $tipoPieza->update($this->validar($request, $tipoPieza));

        return back();
    }

    public function toggle(TipoPieza $tipoPieza): RedirectResponse
    {
        $tipoPieza->update(['activo' => ! $tipoPieza->activo]);

        return back();
    }

    /**
     * @return array{prefijo: string, descripcion: string}
     */
    private function validar(Request $request, ?TipoPieza $tipoPieza = null): array
    {
        $request->merge([
            'prefijo' => mb_strtoupper(trim((string) $request->input('prefijo'))),
        ]);

        /** @var array{prefijo: string, descripcion: string} $datos */
        $datos = $request->validate([
            'prefijo' => [
                'required',
                'string',
                'max:10',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('qal_tipos_pieza', 'prefijo')->ignore($tipoPieza?->id),
            ],
            'descripcion' => ['required', 'string', 'max:255'],
        ], [
            'prefijo.regex' => 'El prefijo sólo admite letras y números, sin espacios ni guiones: es el que abre el segundo tramo de la marca.',
        ], [
            'prefijo' => 'el prefijo',
            'descripcion' => 'la descripción',
        ]);

        return $datos;
    }
}
