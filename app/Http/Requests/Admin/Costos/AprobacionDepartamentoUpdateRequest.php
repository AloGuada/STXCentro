<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class AprobacionDepartamentoUpdateRequest extends FormRequest
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
            'departamento_id' => ['required', 'exists:departamentos,id'],
            'nivel' => ['required', 'integer', 'min:1'],
            'nombre_nivel' => ['required', 'string', 'max:255'],
            'aprobador_id' => ['nullable', 'exists:usuarios,id'],
            'activo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'departamento_id.required' => 'El departamento es obligatorio.',
            'nivel.required' => 'El nivel es obligatorio.',
            'nombre_nivel.required' => 'El nombre del nivel es obligatorio.',
        ];
    }
}
