<?php

namespace App\Http\Requests\Admin\Prod;

use Illuminate\Foundation\Http\FormRequest;

class PagoExtraStoreRequest extends FormRequest
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
            'tipo_id' => ['required', 'exists:prod_tipos,id'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'precio' => ['required', 'numeric', 'min:0'],
            'dias' => ['required', 'integer', 'min:1'],
            'personas' => ['required', 'integer', 'min:1'],
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
            'tipo_id.required' => 'El tipo de pago es obligatorio.',
            'tipo_id.exists' => 'El tipo de pago seleccionado no existe.',
            'precio.required' => 'El precio es obligatorio.',
            'precio.min' => 'El precio no puede ser negativo.',
            'dias.required' => 'Los dias son obligatorios.',
            'dias.min' => 'Debe ser al menos 1 dia.',
            'personas.required' => 'Las personas son obligatorias.',
            'personas.min' => 'Debe ser al menos 1 persona.',
        ];
    }
}
