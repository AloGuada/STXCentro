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
            'descripcion' => ['required', 'string', 'max:255'],
            'tipo_id' => ['required', 'exists:prod_tipos,id'],
            'grupo_trabajo_id' => ['required', 'exists:prod_grupos_trabajo,id'],
            'precio' => ['required', 'numeric', 'min:0'],
            'dias' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'personas' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripcion es obligatoria.',
            'tipo_id.required' => 'El tipo es obligatorio.',
            'grupo_trabajo_id.required' => 'El grupo de trabajo es obligatorio.',
            'precio.required' => 'El precio es obligatorio.',
            'precio.min' => 'El precio no puede ser negativo.',
            'dias.required' => 'Los dias son obligatorios.',
            'dias.min' => 'Los dias deben ser mayores a cero.',
            'dias.decimal' => 'Los dias admiten cuando mucho dos decimales.',
            'personas.required' => 'Las personas son obligatorias.',
            'personas.min' => 'Minimo 1 persona.',
        ];
    }
}
