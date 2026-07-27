<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class EntregaCancelarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('costos.entregas.cancelar') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Indique el motivo de la cancelación.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
        ];
    }
}
