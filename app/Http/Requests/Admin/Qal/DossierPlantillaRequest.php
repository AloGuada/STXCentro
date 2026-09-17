<?php

namespace App\Http\Requests\Admin\Qal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nombre y descripción de una plantilla del dosier. Al crearla se puede
 * partir de otra (`desde`): se copia su árbol entero.
 */
class DossierPlantillaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120', Rule::unique('qal_dossier_plantillas', 'nombre')->ignore($this->route('plantilla'))],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'desde' => ['nullable', 'integer', 'exists:qal_dossier_plantillas,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Ponle nombre a la plantilla.',
            'nombre.unique' => 'Ya hay una plantilla con ese nombre.',
        ];
    }
}
