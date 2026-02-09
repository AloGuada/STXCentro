<?php

namespace App\Http\Requests\Admin\Sti;

use Illuminate\Foundation\Http\FormRequest;

class GenerarMantenimientosRequest extends FormRequest
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
            'plan_id' => ['required', 'integer', 'exists:sti_planes,id'],
            'equipo_id' => ['required', 'integer', 'exists:sti_equipos,id'],
            'fecha_inicial' => ['required', 'date'],
            'year' => ['required', 'integer', 'min:2020'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_id.required' => 'Debe seleccionar un plan.',
            'equipo_id.required' => 'Debe seleccionar un equipo.',
            'fecha_inicial.required' => 'La fecha inicial es obligatoria.',
            'year.required' => 'El año es obligatorio.',
        ];
    }
}
