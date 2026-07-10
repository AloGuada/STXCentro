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
            'opcion_id' => ['required', 'exists:costos_requisicion_cotizacion_opcion,id'],
            'precio_unitario' => ['required', 'numeric', 'min:0.01'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'codigo_producto' => ['nullable', 'string', 'max:255'],
            'moneda' => ['required', 'in:mxn,usd,eur'],
            'tiempo_entrega_dias' => ['nullable', 'integer', 'min:0'],
            'observaciones' => ['nullable', 'string'],
        ];
    }
}
