<?php

namespace App\Http\Requests\Admin\Qal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Un dosier nuevo: la obra —que no puede tener otro— y la plantilla activa de
 * la que nace.
 */
class DossierRequest extends FormRequest
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
            'obra_id' => ['required', 'integer', 'exists:obras,id', Rule::unique('qal_dossiers', 'obra_id')],
            'plantilla_id' => ['required', 'integer', Rule::exists('qal_dossier_plantillas', 'id')->where('activo', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'obra_id.required' => 'Elige la obra.',
            'obra_id.unique' => 'Esa obra ya tiene su dosier.',
            'plantilla_id.required' => 'Elige la plantilla de la que nace.',
            'plantilla_id.exists' => 'Elige una plantilla activa.',
        ];
    }
}
