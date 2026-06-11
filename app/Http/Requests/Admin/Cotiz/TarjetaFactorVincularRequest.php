<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;

class TarjetaFactorVincularRequest extends FormRequest
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
            'factor_id' => ['required', 'exists:cotiz_factores,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'factor_id.required' => 'Selecciona un factor para vincular.',
            'factor_id.exists' => 'El factor seleccionado no existe.',
        ];
    }
}
