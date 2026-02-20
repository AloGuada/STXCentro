<?php

namespace App\Http\Requests\Admin\Infra;

use Illuminate\Foundation\Http\FormRequest;

class TurnoStoreRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'dias_semana' => ['required', 'array', 'min:1'],
            'dias_semana.*' => ['integer', 'between:1,7'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del turno es obligatorio.',
            'hora_inicio.required' => 'La hora de inicio es obligatoria.',
            'hora_fin.required' => 'La hora de fin es obligatoria.',
            'dias_semana.required' => 'Debe seleccionar al menos un día de la semana.',
            'dias_semana.min' => 'Debe seleccionar al menos un día de la semana.',
        ];
    }
}
