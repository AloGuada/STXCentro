<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class EntregaStoreRequest extends FormRequest
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
            'fecha_entrega' => ['required', 'date'],
            'tipo' => ['required', 'in:parcial,completa'],
            'observaciones' => ['nullable', 'string'],
            'archivo' => ['nullable', 'file', 'max:10240'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.orden_compra_detalle_id' => ['required', 'exists:costos_ordenes_compra_detalle,id'],
            'detalles.*.cantidad_recibida' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.precio_unitario' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.observaciones' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'detalles.required' => 'Debe registrar al menos una partida recibida.',
            'detalles.min' => 'Debe registrar al menos una partida recibida.',
            'detalles.*.cantidad_recibida.min' => 'La cantidad recibida debe ser mayor a cero.',
        ];
    }
}
