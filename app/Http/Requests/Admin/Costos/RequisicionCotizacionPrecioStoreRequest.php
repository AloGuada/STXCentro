<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class RequisicionCotizacionPrecioStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('costos.requisiciones.cotizar') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'requisicion_detalle_id' => ['required', 'exists:costos_requisicion_detalle,id'],
            'proveedor_id' => ['required', 'exists:proveedores,id'],
            'precio_unitario' => ['required', 'numeric', 'min:0.01'],
            'tiempo_entrega_dias' => ['nullable', 'integer', 'min:0'],
            'observaciones' => ['nullable', 'string'],
        ];
    }
}
