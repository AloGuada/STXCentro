<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class SolicitudPagoStoreRequest extends FormRequest
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
            'detalles.*.obra_rubro_id' => ['required', 'exists:costos_obra_rubros,id'],
            'detalles.*.concepto' => ['required', 'string', 'max:255'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'monto_total' => ['nullable', 'numeric', 'min:0.01'],
            'archivos' => ['nullable', 'array'],
            'archivos.*' => ['nullable', 'array'],
            'archivos.*.*' => ['file', 'max:10240'],
        ];
    }

    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Contracts\Validation\Validator $validator) {
            // Las obras que no requieren desglose de rubros capturan el total
            // directamente; sin desglose ni total, la solicitud quedaría en cero.
            if (empty($this->input('detalles', [])) && ! $this->filled('monto_total')) {
                $validator->errors()->add('monto_total', 'Captura el desglose de rubros o el total del pago.');
            }
        });
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
            'detalles.*.obra_rubro_id.required' => 'El centro de costos es obligatorio.',
            'detalles.*.concepto.required' => 'El concepto del detalle es obligatorio.',
            'detalles.*.cantidad.required' => 'La cantidad es obligatoria.',
            'detalles.*.precio_unitario.required' => 'El precio unitario es obligatorio.',
        ];
    }
}
