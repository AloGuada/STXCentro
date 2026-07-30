<?php

namespace App\Http\Requests\Admin\Prod;

use Illuminate\Foundation\Http\FormRequest;

class ConceptoUpdateRequest extends FormRequest
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
            'marca' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:255'],
            'cantidad' => ['required', 'integer', 'min:0'],
            'peso_unitario' => ['required', 'numeric', 'min:0'],
            'longitud' => ['required', 'integer', 'min:0'],
            'categoria_id' => ['required', 'exists:prod_categorias,id'],
            'version' => ['nullable', 'integer', 'min:1'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'marca.required' => 'La marca es obligatoria.',
            'descripcion.required' => 'La descripcion es obligatoria.',
            'peso_unitario.required' => 'El peso unitario es obligatorio.',
            'longitud.required' => 'La longitud es obligatoria.',
            'categoria_id.required' => 'La categoria es obligatoria.',
            'categoria_id.exists' => 'La categoria seleccionada no existe.',
        ];
    }
}
