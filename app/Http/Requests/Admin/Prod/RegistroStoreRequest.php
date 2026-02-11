<?php

namespace App\Http\Requests\Admin\Prod;

use Illuminate\Foundation\Http\FormRequest;

class RegistroStoreRequest extends FormRequest
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
            'fecha' => ['required', 'date'],
            'concepto_id' => ['required', 'exists:conceptos,id'],
            'grupo_trabajo_id' => ['required', 'exists:prod_grupos_trabajo,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es obligatoria.',
            'concepto_id.required' => 'El concepto es obligatorio.',
            'concepto_id.exists' => 'El concepto seleccionado no existe.',
            'grupo_trabajo_id.required' => 'El grupo de trabajo es obligatorio.',
            'grupo_trabajo_id.exists' => 'El grupo de trabajo seleccionado no existe.',
            'cantidad.required' => 'La cantidad es obligatoria.',
            'cantidad.min' => 'La cantidad debe ser al menos 1.',
        ];
    }
}
