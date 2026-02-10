<?php

namespace App\Http\Requests\Admin\Prod;

use Illuminate\Foundation\Http\FormRequest;

class FabricadoStoreRequest extends FormRequest
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
            'dest_grupo_id' => ['required', 'exists:prod_grupos,id'],
            'pieza_id' => ['required', 'exists:piezas,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'porcentual' => ['required', 'numeric', 'min:0', 'max:100'],
            'precio_unitario_aplicado' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dest_grupo_id.required' => 'El grupo es obligatorio.',
            'dest_grupo_id.exists' => 'El grupo seleccionado no existe.',
            'pieza_id.required' => 'La pieza es obligatoria.',
            'pieza_id.exists' => 'La pieza seleccionada no existe.',
            'cantidad.required' => 'La cantidad es obligatoria.',
            'porcentual.required' => 'El porcentaje es obligatorio.',
            'porcentual.max' => 'El porcentaje no puede ser mayor a 100.',
            'precio_unitario_aplicado.required' => 'El precio unitario es obligatorio.',
        ];
    }
}
