<?php

namespace App\Http\Requests\Admin\Sti;

use Illuminate\Foundation\Http\FormRequest;

class AsignacionActivoUpdateRequest extends FormRequest
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
            'departamento_id' => ['required', 'integer', 'exists:departamentos,id'],
            'equipo_id' => ['required', 'integer', 'exists:sti_equipos,id'],
            'no_empleado' => ['required', 'string', 'max:50'],
            'empleado' => ['required', 'string', 'max:255'],
            'firma_empleado' => ['nullable', 'string'],
            'no_ti' => ['required', 'string', 'max:50'],
            'nombre_ti' => ['required', 'string', 'max:255'],
            'firma_ti' => ['nullable', 'string'],
            'fecha_inicial' => ['required', 'date'],
            'fecha_termino' => ['nullable', 'date', 'after_or_equal:fecha_inicial'],
            'estado' => ['required', 'string', 'in:activo,devuelto,transferido'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'departamento_id.required' => 'El departamento es obligatorio.',
            'equipo_id.required' => 'El equipo es obligatorio.',
            'no_empleado.required' => 'El numero de empleado es obligatorio.',
            'empleado.required' => 'El nombre del empleado es obligatorio.',
            'no_ti.required' => 'El numero de TI es obligatorio.',
            'nombre_ti.required' => 'El nombre de TI es obligatorio.',
            'fecha_inicial.required' => 'La fecha inicial es obligatoria.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado debe ser: activo, devuelto o transferido.',
            'fecha_termino.after_or_equal' => 'La fecha de termino debe ser igual o posterior a la fecha inicial.',
        ];
    }
}
