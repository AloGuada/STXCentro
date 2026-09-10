<?php

namespace App\Http\Requests\Admin\Prod;

use Illuminate\Foundation\Http\FormRequest;

class TipoPagoExtraUpdateRequest extends FormRequest
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
            'orden' => ['required', 'integer', 'min:0'],
            'desgloce' => ['required', 'boolean'],
            'es_descuento' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripcion es obligatoria.',
            'orden.required' => 'El orden es obligatorio.',
            'orden.min' => 'El orden no puede ser negativo.',
        ];
    }
}
