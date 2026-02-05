<?php

namespace App\Http\Requests\Admin\Sti;

use Illuminate\Foundation\Http\FormRequest;

class TicketUpdateRequest extends FormRequest
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
            'nombre_solicitante' => ['required', 'string', 'max:255'],
            'comentario' => ['required', 'string'],
            'tecnico_id' => ['nullable', 'integer', 'exists:sti_tecnicos,id'],
            'equipo_id' => ['nullable', 'integer', 'exists:sti_equipos,id'],
            'departamento_id' => ['required', 'integer', 'exists:departamentos,id'],
            'status_id' => ['nullable', 'integer', 'exists:sti_status,id'],
            'firma_completado' => ['nullable', 'string'],
            'calificacion' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre_solicitante.required' => 'El nombre del solicitante es obligatorio.',
            'comentario.required' => 'El comentario es obligatorio.',
            'departamento_id.required' => 'El departamento es obligatorio.',
            'departamento_id.exists' => 'El departamento seleccionado no existe.',
        ];
    }
}
