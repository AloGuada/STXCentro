<?php

namespace App\Http\Requests\Admin\Rh;

use Illuminate\Foundation\Http\FormRequest;

class PeriodoLaboralUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'persona_id' => ['required', 'exists:rh_personas,id'],
            'puesto_id' => ['required', 'exists:rh_puestos,id'],
            'requisicion_id' => ['nullable', 'exists:rh_requisiciones,id'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'estado' => ['required', 'string', 'in:activo,terminado,baja'],
            'salario' => ['nullable', 'numeric', 'min:0'],
            'tipo_contrato' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'persona_id.required' => 'La persona es obligatoria.',
            'puesto_id.required' => 'El puesto es obligatorio.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
        ];
    }
}
