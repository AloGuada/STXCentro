<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class OrdenCompraUpdateRequest extends FormRequest
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
            'referencia' => ['nullable', 'string', 'max:100'],
            'proveedor_id' => ['required', 'exists:proveedores,id'],
            'obra_id' => ['nullable', 'exists:obras,id'],
            'departamento_id' => ['required', 'exists:departamentos,id'],
            'moneda' => ['required', 'in:mxn,usd,eur'],
            'total' => ['required', 'numeric', 'min:0.01'],
            'fecha_entrega_esperada' => ['required', 'date'],
            'notas' => ['nullable', 'string'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id' => ['nullable', 'integer'],
            'detalles.*.obra_rubro_id' => ['required', 'exists:costos_obra_rubros,id'],
            'detalles.*.monto' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
