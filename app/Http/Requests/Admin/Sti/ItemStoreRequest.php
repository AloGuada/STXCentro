<?php

namespace App\Http\Requests\Admin\Sti;

use Illuminate\Foundation\Http\FormRequest;

class ItemStoreRequest extends FormRequest
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
            'descripcion' => ['required', 'string', 'max:255'],
            'tipo_id' => ['required', 'exists:sti_items_tipos,id'],
            'costo' => ['required', 'numeric', 'min:0'],
            'no_serie' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'in:disponible,instalado,dañado,baja'],
            'principal' => ['boolean'],
            'accesorio' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripción es obligatoria.',
            'tipo_id.required' => 'El tipo de item es obligatorio.',
            'tipo_id.exists' => 'El tipo de item seleccionado no existe.',
            'costo.required' => 'El costo es obligatorio.',
            'costo.min' => 'El costo no puede ser negativo.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado seleccionado no es válido.',
        ];
    }
}
