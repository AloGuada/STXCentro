<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class RequisicionLiberarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('costos.requisiciones.liberar') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ocs' => ['required', 'array', 'min:1'],
            'ocs.*.proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'ocs.*.numero_oc' => ['required', 'integer', 'min:1', 'max:50'],
            'ocs.*.modo_pago' => ['required', 'in:contado,credito'],
            'ocs.*.moneda' => ['required', 'in:mxn,usd,eur'],
            'ocs.*.notas' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ocs.required' => 'No hay órdenes de compra para liberar.',
            'ocs.*.modo_pago.required' => 'Captura el modo de pago de cada OC.',
            'ocs.*.moneda.required' => 'Captura la moneda de cada OC.',
        ];
    }
}
