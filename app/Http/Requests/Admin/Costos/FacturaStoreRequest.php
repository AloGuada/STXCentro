<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class FacturaStoreRequest extends FormRequest
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
            'orden_compra_id' => ['required', 'exists:costos_ordenes_compra,id'],
            'uuid_fiscal' => ['nullable', 'string', 'max:255', 'unique:costos_facturas,uuid_fiscal'],
            'folio_fiscal' => ['nullable', 'string', 'max:255'],
            'fecha_factura' => ['nullable', 'date'],
            'iva' => ['nullable', 'numeric', 'min:0'],
            'moneda' => ['required', 'in:mxn,usd,eur'],
            'notas' => ['nullable', 'string'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.orden_compra_detalle_id' => ['required', 'exists:costos_ordenes_compra_detalle,id'],
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
            'orden_compra_id.required' => 'Seleccione la orden de compra.',
            'detalles.required' => 'Debe registrar al menos una partida de factura.',
            'detalles.min' => 'Debe registrar al menos una partida de factura.',
            'detalles.*.cantidad.min' => 'La cantidad facturada debe ser mayor a cero.',
            'uuid_fiscal.unique' => 'Ya existe una factura con este UUID fiscal.',
        ];
    }
}
