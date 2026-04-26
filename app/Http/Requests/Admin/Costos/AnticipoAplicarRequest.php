<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class AnticipoAplicarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('costos.anticipos.aplicar') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'anticipo_id' => ['required', 'exists:costos_anticipos,id'],
            'factura_id' => ['required', 'exists:costos_facturas,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'notas' => ['nullable', 'string'],
        ];
    }
}
