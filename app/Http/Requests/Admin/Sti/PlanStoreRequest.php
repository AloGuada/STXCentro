<?php

namespace App\Http\Requests\Admin\Sti;

use Illuminate\Foundation\Http\FormRequest;

class PlanStoreRequest extends FormRequest
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
            'equipo_id' => ['required', 'integer', 'exists:sti_equipos,id'],
            'descripcion' => ['required', 'string', 'max:255'],
            'periodicidad' => ['required', 'integer', 'min:1'],
            'fecha_inicial' => ['required', 'date'],
            'activo' => ['boolean'],
            'checks' => ['nullable', 'array'],
            'checks.*.descripcion' => ['required_with:checks', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'equipo_id.required' => 'El equipo es obligatorio.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'periodicidad.required' => 'La periodicidad es obligatoria.',
            'periodicidad.min' => 'La periodicidad debe ser al menos 1 día.',
            'fecha_inicial.required' => 'La fecha inicial es obligatoria.',
        ];
    }
}
