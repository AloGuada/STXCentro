<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class SolicitudPagoUpdateRequest extends FormRequest
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
            'departamento_id' => ['required', 'exists:departamentos,id'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'tipo_solicitud_id' => ['required', 'exists:costos_tipo_solicitud,id'],
            'concepto' => ['required', 'string'],
            'tipo_pago' => ['required', 'string', 'in:transferencia,cheque,efectivo'],
            'tipo_moneda' => ['required', 'string', 'in:mxn,usd,eur'],
            'fecha_pago_solicitada' => ['nullable', 'date'],
            'detalles' => ['nullable', 'array'],
            'detalles.*.id' => ['nullable', 'integer'],
            'detalles.*.obra_rubro_id' => ['required', 'exists:costos_obra_rubros,id'],
            'detalles.*.concepto' => ['required', 'string', 'max:255'],
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
            'departamento_id.required' => 'El departamento es obligatorio.',
            'tipo_solicitud_id.required' => 'El tipo de solicitud es obligatorio.',
            'concepto.required' => 'El concepto es obligatorio.',
            'tipo_pago.required' => 'El tipo de pago es obligatorio.',
            'detalles.*.obra_rubro_id.required' => 'El rubro es obligatorio.',
            'detalles.*.concepto.required' => 'El concepto del detalle es obligatorio.',
            'detalles.*.cantidad.required' => 'La cantidad es obligatoria.',
            'detalles.*.precio_unitario.required' => 'El precio unitario es obligatorio.',
        ];
    }
}
