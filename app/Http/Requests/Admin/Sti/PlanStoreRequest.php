<?php

namespace App\Http\Requests\Admin\Sti;

use Illuminate\Foundation\Http\FormRequest;

class PlanStoreRequest extends FormRequest
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
            'descripcion' => ['required', 'string', 'max:255'],
            'periodicidad' => ['required', 'integer', 'min:1'],
            'activo' => ['boolean'],
            'checks' => ['nullable', 'array'],
            'checks.*.descripcion' => ['required_with:checks', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripción es obligatoria.',
            'periodicidad.required' => 'La periodicidad es obligatoria.',
            'periodicidad.min' => 'La periodicidad debe ser al menos 1 día.',
        ];
    }
}
