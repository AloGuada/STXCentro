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
            'obra_id' => ['required', 'exists:obras,id'],
            'marca' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:255'],
            'peso_unitario' => ['required', 'numeric', 'min:0'],
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
            'obra_id.required' => 'La obra es obligatoria.',
            'obra_id.exists' => 'La obra seleccionada no existe.',
            'marca.required' => 'La marca es obligatoria.',
            'descripcion.required' => 'La descripcion es obligatoria.',
            'peso_unitario.required' => 'El peso unitario es obligatorio.',
        ];
    }
}
