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
            'estado' => ['required', 'string', 'in:activo,baja'],
            'salario_diario' => ['required', 'numeric', 'min:0'],
            'sueldo_mensual' => ['required', 'numeric', 'min:0'],
            'sueldo_real' => ['nullable', 'numeric', 'min:0'],
            'periodicidad_pago' => ['nullable', 'string', 'in:semanal,quincenal,mensual'],
            'tipo_salario' => ['nullable', 'string', 'in:fijo,destajo'],
            'tipo_contrato' => ['nullable', 'string', 'max:255'],
            'numero_empleado' => ['nullable', 'string', 'max:50'],
            'tipo_empleado' => ['nullable', 'string', 'in:planta,contratista,becario,foraneo'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'persona_id.required' => 'La persona es obligatoria.',
            'puesto_id.required' => 'El puesto es obligatorio.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'salario_diario.required' => 'El salario diario es obligatorio.',
            'sueldo_mensual.required' => 'El sueldo mensual es obligatorio.',
        ];
    }
}
