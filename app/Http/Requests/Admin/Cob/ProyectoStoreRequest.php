<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProyectoStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'no' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:255'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'tipo_contrato' => ['nullable', Rule::in(['precio_unitario', 'alzado'])],
            'monto' => ['nullable', 'numeric', 'min:0'],
            'monto_iva' => ['nullable', 'numeric', 'min:0'],
            'anticipo' => ['nullable', 'numeric', 'min:0'],
            'garantia' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
