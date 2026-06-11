<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;

class ObraInsumoOverrideUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Todos los campos son opcionales: cada uno NULL = "sin override" (cae al global).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'descripcion' => ['nullable', 'string', 'max:255'],
            'codigo_stumis' => ['nullable', 'string', 'max:255'],
            'unidad_id' => ['nullable', 'integer', 'exists:cotiz_unidades,id'],
            'precio_unitario' => ['nullable', 'numeric', 'min:0'],
            'peso_lineal' => ['nullable', 'numeric', 'min:0'],
            'peso_default' => ['nullable', 'numeric', 'min:0'],
            'centro_costo_id' => ['nullable', 'integer', 'exists:cotiz_centros_costos,id'],
            'comentario' => ['nullable', 'string', 'max:255'],
        ];
    }
}
