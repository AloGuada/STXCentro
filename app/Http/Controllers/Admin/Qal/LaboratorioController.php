<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Models\Qal\Laboratorio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Laboratorios que firman los informes de ensayos no destructivos.
 *
 * Tiene controlador propio y no la base simple porque lleva siglas, que es como
 * se le nombra dentro del informe.
 */
class LaboratorioController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Laboratorio::create($this->validar($request) + ['activo' => true]);

        return back();
    }

    public function update(Request $request, Laboratorio $laboratorio): RedirectResponse
    {
        $laboratorio->update($this->validar($request, $laboratorio));

        return back();
    }

    public function toggle(Laboratorio $laboratorio): RedirectResponse
    {
        $laboratorio->update(['activo' => ! $laboratorio->activo]);

        return back();
    }

    /**
     * @return array{nombre: string, siglas: string|null}
     */
    private function validar(Request $request, ?Laboratorio $laboratorio = null): array
    {
        /** @var array{nombre: string, siglas: string|null} $datos */
        $datos = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('qal_laboratorios', 'nombre')->ignore($laboratorio?->id),
            ],
            'siglas' => ['nullable', 'string', 'max:20'],
        ], [], [
            'nombre' => 'el laboratorio',
            'siglas' => 'las siglas',
        ]);

        return $datos;
    }
}
