<?php

namespace App\Http\Requests\Admin\Drive;

use Illuminate\Foundation\Http\FormRequest;

class CarpetaAccesoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'usuario_id' => ['required', 'uuid', 'exists:usuarios,id'],
            'puede_escribir' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'usuario_id.required' => 'Selecciona el usuario al que le vas a dar acceso.',
            'usuario_id.exists' => 'El usuario seleccionado no existe.',
        ];
    }
}
