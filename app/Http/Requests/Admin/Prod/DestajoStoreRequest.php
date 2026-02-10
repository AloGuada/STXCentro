<?php

namespace App\Http\Requests\Admin\Prod;

use Illuminate\Foundation\Http\FormRequest;

class DestajoStoreRequest extends FormRequest
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
            'semana' => ['required', 'integer', 'min:1', 'max:53'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'semana.required' => 'La semana es obligatoria.',
            'semana.integer' => 'La semana debe ser un numero entero.',
            'semana.min' => 'La semana debe ser al menos 1.',
            'semana.max' => 'La semana no puede ser mayor a 53.',
        ];
    }
}
