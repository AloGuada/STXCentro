<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Models\Qal\Soldador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Padrón de soldadores.
 *
 * La clave es única porque es la que se estampa en la pieza y la que enlaza al
 * soldador con su WPQR en el dosier: dos soldadores con la misma clave hacen
 * imposible saber quién soldó qué.
 */
class SoldadorController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Soldador::create($this->validar($request) + ['activo' => true]);

        return back();
    }

    public function update(Request $request, Soldador $soldador): RedirectResponse
    {
        $soldador->update($this->validar($request, $soldador));

        return back();
    }

    public function toggle(Soldador $soldador): RedirectResponse
    {
        $soldador->update(['activo' => ! $soldador->activo]);

        return back();
    }

    /**
     * @return array{nombre: string, clave: string|null, certificacion: string|null, certificacion_vence_at: string|null}
     */
    private function validar(Request $request, ?Soldador $soldador = null): array
    {
        $clave = trim((string) $request->input('clave'));
        $request->merge(['clave' => $clave === '' ? null : mb_strtoupper($clave)]);

        /** @var array{nombre: string, clave: string|null, certificacion: string|null, certificacion_vence_at: string|null} $datos */
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'clave' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('qal_soldadores', 'clave')->ignore($soldador?->id),
            ],
            'certificacion' => ['nullable', 'string', 'max:255'],
            'certificacion_vence_at' => ['nullable', 'date'],
        ], [], [
            'nombre' => 'el nombre',
            'clave' => 'la clave',
            'certificacion' => 'la certificación',
            'certificacion_vence_at' => 'la vigencia',
        ]);

        return $datos;
    }
}
