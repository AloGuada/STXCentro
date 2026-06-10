<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;

class PinturaFormulaStoreRequest extends FormRequest
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
            'clave' => ['required', 'string', 'max:100', 'unique:cotiz_pintura_formulas,clave'],
            'nombre' => ['required', 'string', 'max:255'],
            'formula' => ['required', 'string', 'max:500'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'clave.required' => 'La clave es obligatoria.',
            'clave.unique' => 'Esta clave ya está registrada.',
            'nombre.required' => 'El nombre es obligatorio.',
            'formula.required' => 'La fórmula es obligatoria.',
            'orden.integer' => 'El orden debe ser un número entero.',
            'orden.min' => 'El orden no puede ser negativo.',
        ];
    }
}
