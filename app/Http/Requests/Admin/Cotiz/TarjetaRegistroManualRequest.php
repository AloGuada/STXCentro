<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;

class TarjetaRegistroManualRequest extends FormRequest
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
            'insumo_id' => ['required', 'exists:cotiz_insumos,id'],
            'cantidad' => ['nullable', 'numeric'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'insumo_id.required' => 'Selecciona un insumo para el registro manual.',
            'insumo_id.exists' => 'El insumo seleccionado no existe.',
        ];
    }
}
