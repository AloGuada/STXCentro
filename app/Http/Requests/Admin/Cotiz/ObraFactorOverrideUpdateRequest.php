<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;

class ObraFactorOverrideUpdateRequest extends FormRequest
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
            'nombre' => ['nullable', 'string', 'max:255'],
            'insumo_id' => ['nullable', 'integer', 'exists:cotiz_insumos,id'],
            'formula' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'comentario' => ['nullable', 'string', 'max:255'],
        ];
    }
}
