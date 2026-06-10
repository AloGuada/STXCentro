<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;

class GeneradoraRegistroUpdateRequest extends FormRequest
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
            'material_origen_id' => ['nullable', 'exists:cotiz_insumos,id'],
            'material' => ['nullable', 'string', 'max:255'],
            'marca' => ['nullable', 'string', 'max:255'],
            'ancho' => ['nullable', 'numeric', 'min:0'],
            'largo' => ['nullable', 'numeric', 'min:0'],
            'cantidad' => ['nullable', 'numeric', 'min:0'],
            'cant_pzas' => ['nullable', 'numeric', 'min:0'],
            'peso_porcentual' => ['nullable', 'numeric'],
            'kilos_totales' => ['nullable', 'numeric'],
            'merma_id' => ['required', 'exists:cotiz_mermas,id'],
            'validado' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'material_origen_id.exists' => 'El material de origen seleccionado no existe.',
            'merma_id.required' => 'La merma es obligatoria.',
            'merma_id.exists' => 'La merma seleccionada no existe.',
        ];
    }
}
