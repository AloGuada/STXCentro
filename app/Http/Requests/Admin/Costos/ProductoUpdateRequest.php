<?php

namespace App\Http\Requests\Admin\Costos;

use App\Rules\DescripcionUnicaEnCatalogo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('costos.productos.editar') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $producto = $this->route('producto');

        return [
            'codigo' => ['nullable', 'string', 'max:100', Rule::unique('costos_productos', 'codigo')->ignore($producto?->id)],
            'descripcion' => ['required', 'string', 'max:255', DescripcionUnicaEnCatalogo::paraEdicion($producto?->item_id)],
            'unidad' => ['required', 'string', 'max:30'],
            'activo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripción es obligatoria.',
            'codigo.unique' => 'Ya existe un producto con ese código.',
        ];
    }
}
