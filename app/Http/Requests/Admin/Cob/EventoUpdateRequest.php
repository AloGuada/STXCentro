<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;

class EventoUpdateRequest extends FormRequest
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
            'parent_id' => ['nullable', 'exists:cob_eventos,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'monto' => ['nullable', 'numeric', 'min:0'],
            'inicio' => ['nullable', 'date'],
            'fin' => ['nullable', 'date'],
            'marcado' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.exists' => 'El evento padre seleccionado no existe.',
            'nombre.required' => 'El nombre es obligatorio.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.min' => 'El monto debe ser mayor o igual a 0.',
            'marcado.required' => 'El campo marcado es obligatorio.',
        ];
    }
}
