<?php

namespace App\Http\Requests\Admin\Prod;

use Illuminate\Foundation\Http\FormRequest;

class GrupoPrecioStoreRequest extends FormRequest
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
            'descripcion' => ['required', 'string', 'max:255'],
            'precio_kilo' => ['required', 'numeric', 'min:0'],
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
            'descripcion.required' => 'La descripcion es obligatoria.',
            'precio_kilo.required' => 'El precio por kilo es obligatorio.',
            'precio_kilo.min' => 'El precio por kilo no puede ser negativo.',
        ];
    }
}
