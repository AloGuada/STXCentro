<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class OrdenCompraStoreRequest extends FormRequest
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
            'fecha_entrega_esperada' => ['nullable', 'date'],
            'notas' => ['nullable', 'string'],
            'archivo' => ['nullable', 'file', 'max:10240'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.obra_rubro_id' => ['required', 'exists:costos_obra_rubros,id'],
            'detalles.*.monto' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'detalles.required' => 'Debe agregar al menos un detalle.',
            'detalles.min' => 'Debe agregar al menos un detalle.',
            'detalles.*.obra_rubro_id.required' => 'El rubro es obligatorio.',
            'detalles.*.monto.required' => 'El monto es obligatorio.',
            'detalles.*.monto.min' => 'El monto debe ser mayor a cero.',
        ];
    }
}
