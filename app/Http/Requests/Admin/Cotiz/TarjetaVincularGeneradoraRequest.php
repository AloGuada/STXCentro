<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;

class TarjetaVincularGeneradoraRequest extends FormRequest
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
            'generadora_id' => ['required', 'exists:cotiz_generadoras,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'generadora_id.required' => 'Selecciona una generadora para vincular.',
            'generadora_id.exists' => 'La generadora seleccionada no existe.',
        ];
    }
}
