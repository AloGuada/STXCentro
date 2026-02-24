<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;

class PartidaStoreRequest extends FormRequest
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
            'tipo' => ['required', 'string', 'in:suministro,montaje'],
            'es_adicional' => ['sometimes', 'boolean'],
            'descripcion' => ['required', 'string', 'max:255'],
            'monto' => ['required', 'numeric', 'min:0'],
            'moneda' => ['sometimes', 'string', 'max:3'],
            'es_subobra' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.required' => 'El tipo es obligatorio.',
            'tipo.in' => 'El tipo debe ser suministro o montaje.',
            'es_adicional.boolean' => 'El campo adicional debe ser verdadero o falso.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'descripcion.max' => 'La descripción no debe exceder 255 caracteres.',
            'monto.required' => 'El monto es obligatorio.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.min' => 'El monto debe ser mayor o igual a 0.',
            'moneda.required' => 'La moneda es obligatoria.',
            'moneda.max' => 'La moneda no debe exceder 3 caracteres.',
            'es_subobra.required' => 'El campo subobra es obligatorio.',
            'es_subobra.boolean' => 'El campo subobra debe ser verdadero o falso.',
        ];
    }
}
