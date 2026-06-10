<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;

class ObraUpdateRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
            'op' => ['nullable', 'string', 'max:100'],
            'factor_contratista' => ['required', 'numeric', 'min:0'],
            'num_grupos' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la obra es obligatorio.',
            'factor_contratista.required' => 'El factor de contratista es obligatorio.',
            'factor_contratista.min' => 'El factor de contratista no puede ser negativo.',
            'num_grupos.required' => 'El número de grupos es obligatorio.',
            'num_grupos.min' => 'Debe haber al menos un grupo.',
        ];
    }
}
