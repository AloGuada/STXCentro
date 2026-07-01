<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class FirmarRequisicionFinalRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Aprobador = usuario asignado en costos_aprobacion_departamento
        // (gate 'aprobador-costos'). Fuente única de "quién puede firmar".
        return $this->user()?->can('aprobador-costos') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'observaciones' => ['required', 'string', 'max:500'],
            'validaciones' => ['present', 'array'],
            'validaciones.*.proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'validaciones.*.accion' => ['required', 'in:activar,rechazar'],
            'validaciones.*.observacion' => ['nullable', 'string', 'max:500'],
            'validaciones.*.reemplazos' => ['nullable', 'array'],
            'validaciones.*.reemplazos.*.requisicion_detalle_id' => ['required', 'integer', 'exists:costos_requisicion_detalle,id'],
            'validaciones.*.reemplazos.*.nuevo_proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'validaciones.*.reemplazos.*.cotizacion_precio_id' => ['required', 'integer', 'exists:costos_requisicion_cotizacion_precio,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'observaciones.required' => 'Agregue sus observaciones para firmar.',
        ];
    }
}
