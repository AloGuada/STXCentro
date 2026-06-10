<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FactorUpdateRequest extends FormRequest
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
            'codigo' => ['required', 'string', 'max:100', Rule::unique('cotiz_factores', 'codigo')->ignore($this->route('factor'))],
            'nombre' => ['required', 'string', 'max:255'],
            'insumo_id' => ['required', 'exists:cotiz_insumos,id'],
            'formula' => ['nullable', 'string', 'max:500'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'categoria_tarjeta_id' => ['nullable', 'exists:cotiz_categorias_tarjeta,id'],
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
            'insumo_id.required' => 'El insumo es obligatorio.',
            'insumo_id.exists' => 'El insumo seleccionado no existe.',
            'categoria_tarjeta_id.exists' => 'La categoría de tarjeta seleccionada no existe.',
        ];
    }
}
