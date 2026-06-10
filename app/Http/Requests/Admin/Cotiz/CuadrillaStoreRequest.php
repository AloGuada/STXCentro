<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;

class CuadrillaStoreRequest extends FormRequest
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
            'codigo' => ['required', 'string', 'max:100', 'unique:cotiz_cuadrillas,codigo'],
            'nombre' => ['required', 'string', 'max:255'],
            'centro_costo_id' => ['required', 'exists:cotiz_centros_costos,id'],
            'insumo_id' => ['nullable', 'exists:cotiz_insumos,id'],
            'rendimiento' => ['nullable', 'numeric', 'min:0'],
            'formula_costo' => ['nullable', 'string', 'max:500'],
            'descripcion' => ['nullable', 'string', 'max:500'],
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
            'centro_costo_id.required' => 'El centro de costo es obligatorio.',
            'centro_costo_id.exists' => 'El centro de costo seleccionado no existe.',
            'insumo_id.exists' => 'El insumo seleccionado no existe.',
            'rendimiento.min' => 'El rendimiento no puede ser negativo.',
        ];
    }
}
