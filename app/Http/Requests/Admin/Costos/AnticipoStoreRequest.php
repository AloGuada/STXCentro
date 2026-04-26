<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class AnticipoStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('costos.anticipos.crear') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'proveedor_id' => ['required', 'exists:proveedores,id'],
            'obra_id' => ['nullable', 'exists:obras,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'moneda' => ['required', 'in:mxn,usd,eur'],
            'fecha' => ['required', 'date'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proveedor_id.required' => 'Selecciona un proveedor.',
            'monto.min' => 'El monto debe ser mayor a cero.',
        ];
    }
}
