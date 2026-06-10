<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CentroCostoUpdateRequest extends FormRequest
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
            'cod_coste' => ['required', 'string', 'max:100', Rule::unique('cotiz_centros_costos', 'cod_coste')->ignore($this->route('centroCosto'))],
            'concepto' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cod_coste.required' => 'El código de coste es obligatorio.',
            'cod_coste.unique' => 'Este código de coste ya está registrado.',
            'concepto.required' => 'El concepto es obligatorio.',
        ];
    }
}
