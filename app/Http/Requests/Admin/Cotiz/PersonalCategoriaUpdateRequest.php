<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonalCategoriaUpdateRequest extends FormRequest
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
            'codigo' => ['required', 'string', 'max:100', Rule::unique('cotiz_personal_categorias', 'codigo')->ignore($this->route('personal'))],
            'nombre' => ['required', 'string', 'max:255'],
            'sueldo_semanal' => ['required', 'numeric', 'min:0'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique' => 'Este código ya está registrado.',
            'nombre.required' => 'El nombre es obligatorio.',
            'sueldo_semanal.required' => 'El sueldo semanal es obligatorio.',
            'sueldo_semanal.min' => 'El sueldo semanal no puede ser negativo.',
            'orden.integer' => 'El orden debe ser un número entero.',
            'orden.min' => 'El orden no puede ser negativo.',
        ];
    }
}
