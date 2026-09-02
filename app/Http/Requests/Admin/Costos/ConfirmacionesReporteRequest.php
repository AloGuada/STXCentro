<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmacionesReporteRequest extends FormRequest
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
            'paso' => ['required', 'in:costos,contabilidad'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paso.required' => 'Indica qué bandeja se exporta.',
            'paso.in' => 'La bandeja debe ser Costos o Contabilidad.',
        ];
    }
}
