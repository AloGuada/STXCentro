<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class PermisoStoreRequest extends FormRequest
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
            'nivel' => ['required', 'integer', 'min:1'],
            'tipo_aprobacion' => ['required', 'in:solicitud_pago,requisicion'],
            'omitir_si_presupuesto_reservado' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripcion es obligatoria.',
            'nivel.required' => 'El nivel es obligatorio.',
        ];
    }
}
