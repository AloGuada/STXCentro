<?php

namespace App\Http\Requests\Admin\Sti;

use Illuminate\Foundation\Http\FormRequest;

class MantenimientoStoreRequest extends FormRequest
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
            'fecha_programada' => ['required', 'date'],
            'descripcion' => ['nullable', 'string'],
            'tecnico_id' => ['required', 'integer', 'exists:sti_tecnicos,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'equipo_id.required' => 'El equipo es obligatorio.',
            'fecha_programada.required' => 'La fecha programada es obligatoria.',
            'tecnico_id.required' => 'El técnico es obligatorio.',
        ];
    }
}
