<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;

class TarjetaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'obra_id' => ['required', 'exists:cotiz_obras,id'],
            'descripcion' => ['required', 'string', 'max:255'],
            'orden' => ['nullable', 'integer'],
            // Generadora inicial opcional: si se pasa, se vincula y se importan sus registros.
            'generadora_id' => ['nullable', 'exists:cotiz_generadoras,id'],
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
            'descripcion.required' => 'La descripción de la tarjeta es obligatoria.',
        ];
    }
}
