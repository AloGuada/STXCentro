<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegimenFiscalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'clave' => ['required', 'string', 'max:10', Rule::unique('regimenes_fiscales', 'clave')->ignore($this->route('regimenFiscal'))],
            'descripcion' => ['required', 'string', 'max:255'],
            'aplica_persona_fisica' => ['boolean'],
            'aplica_persona_moral' => ['boolean'],
            'activo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'clave.required' => 'La clave es obligatoria.',
            'clave.unique' => 'Esta clave ya está registrada.',
            'descripcion.required' => 'La descripción es obligatoria.',
        ];
    }
}
