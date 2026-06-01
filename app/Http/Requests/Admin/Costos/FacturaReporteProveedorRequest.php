<?php

namespace App\Http\Requests\Admin\Costos;

class FacturaReporteProveedorRequest extends FacturaReporteRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'proveedor_id' => ['required', 'exists:proveedores,id'],
        ];
    }
}
