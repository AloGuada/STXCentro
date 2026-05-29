<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class UsoCfdiStoreRequest extends FormRequest
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
            'clave' => ['required', 'string', 'max:10', 'unique:costos_usos_cfdi,clave'],
            'descripcion' => ['required', 'string', 'max:255'],
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
