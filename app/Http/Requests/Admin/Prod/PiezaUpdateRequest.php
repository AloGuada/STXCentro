<?php

namespace App\Http\Requests\Admin\Prod;

use Illuminate\Foundation\Http\FormRequest;

class PiezaUpdateRequest extends FormRequest
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
            'longitud' => ['nullable', 'numeric', 'min:0'],
            'peso' => ['required', 'numeric', 'min:0'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'version' => ['nullable', 'integer', 'min:1'],
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
            'peso.required' => 'El peso es obligatorio.',
            'cantidad.required' => 'La cantidad es obligatoria.',
            'cantidad.min' => 'La cantidad debe ser al menos 1.',
        ];
    }
}
