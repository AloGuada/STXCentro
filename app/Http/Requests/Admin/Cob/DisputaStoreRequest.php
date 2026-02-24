<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;

class DisputaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'descripcion' => ['required', 'string'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_resolucion' => ['nullable', 'date'],
            'estado' => ['required', 'string', 'in:en_proceso,resuelto,cancelado'],
            'resultado' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripción es obligatoria.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado debe ser: en proceso, resuelto o cancelado.',
        ];
    }
}
