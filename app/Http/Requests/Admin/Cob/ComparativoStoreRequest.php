<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;

class ComparativoStoreRequest extends FormRequest
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
            'obra_id' => ['required', 'integer', 'exists:obras,id'],
            'descripcion' => ['required', 'string'],
            'monto_impacto' => ['required', 'numeric', 'min:0'],
            'fecha_identificacion' => ['nullable', 'date'],
            'estado' => ['required', 'string', 'in:analisis,aprobado,implementado,descartado'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'obra_id.required' => 'La obra es obligatoria.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'monto_impacto.required' => 'El monto de impacto es obligatorio.',
            'monto_impacto.numeric' => 'El monto de impacto debe ser un número.',
            'monto_impacto.min' => 'El monto de impacto debe ser mayor o igual a 0.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado debe ser análisis, aprobado, implementado o descartado.',
        ];
    }
}
