<?php

namespace App\Http\Requests\Sti;

use Illuminate\Foundation\Http\FormRequest;

class TicketPublicoStoreRequest extends FormRequest
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
            'comentario' => ['required', 'string', 'max:2000'],
            'departamento_id' => ['required', 'integer', 'exists:departamentos,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre_solicitante.required' => 'Tu nombre es obligatorio.',
            'comentario.required' => 'El comentario es obligatorio.',
            'departamento_id.required' => 'Selecciona tu departamento.',
            'departamento_id.exists' => 'El departamento seleccionado no es válido.',
        ];
    }
}
