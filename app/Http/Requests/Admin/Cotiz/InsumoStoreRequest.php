<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;

class InsumoStoreRequest extends FormRequest
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
            'descripcion' => ['required', 'string', 'max:255', 'unique:cotiz_insumos,descripcion'],
            'codigo_stumis' => ['nullable', 'string', 'max:100'],
            'unidad_id' => ['required', 'exists:cotiz_unidades,id'],
            'precio_unitario' => ['required', 'numeric', 'min:0'],
            'peso_lineal' => ['nullable', 'numeric', 'min:0'],
            'peso_default' => ['nullable', 'numeric', 'min:0'],
            'centro_costo_id' => ['required', 'exists:cotiz_centros_costos,id'],
            'categoria_tarjeta_id' => ['nullable', 'exists:cotiz_categorias_tarjeta,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripción es obligatoria.',
            'descripcion.unique' => 'Esta descripción ya está registrada.',
            'unidad_id.required' => 'La unidad es obligatoria.',
            'unidad_id.exists' => 'La unidad seleccionada no existe.',
            'precio_unitario.required' => 'El precio unitario es obligatorio.',
            'precio_unitario.min' => 'El precio unitario no puede ser negativo.',
            'centro_costo_id.required' => 'El centro de costo es obligatorio.',
            'centro_costo_id.exists' => 'El centro de costo seleccionado no existe.',
            'categoria_tarjeta_id.exists' => 'La categoría de tarjeta seleccionada no existe.',
        ];
    }
}
