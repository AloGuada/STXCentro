<?php

namespace App\Http\Requests\Admin\Qal;

use Illuminate\Foundation\Http\FormRequest;

class ConfiguracionQalUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'formularios_segun_avance' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'formularios_segun_avance.required' => 'Indica si Formularios se limita al avance de producción.',
            'formularios_segun_avance.boolean' => 'El valor debe ser encendido o apagado.',
        ];
    }
}
