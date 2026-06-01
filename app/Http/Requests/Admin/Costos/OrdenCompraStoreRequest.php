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
            'fecha_entrega_esperada' => ['required', 'date', 'after_or_equal:today'],
            'notas' => ['nullable', 'string'],
            'archivo' => ['nullable', 'file', 'max:10240'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.obra_rubro_id' => ['required', 'exists:costos_obra_rubros,id'],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.unidad' => ['required', 'string', 'max:20'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_entrega_esperada.required' => 'La fecha de entrega esperada es obligatoria.',
            'fecha_entrega_esperada.after_or_equal' => 'La fecha de entrega no puede ser anterior a hoy.',
            'detalles.required' => 'Debe agregar al menos un detalle.',
            'detalles.min' => 'Debe agregar al menos un detalle.',
            'detalles.*.obra_rubro_id.required' => 'El rubro es obligatorio.',
            'detalles.*.descripcion.required' => 'La descripción de la partida es obligatoria.',
            'detalles.*.unidad.required' => 'La unidad es obligatoria.',
            'detalles.*.cantidad.required' => 'La cantidad es obligatoria.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a cero.',
            'detalles.*.precio_unitario.required' => 'El precio unitario es obligatorio.',
        ];
    }
}
