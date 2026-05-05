<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class RequisicionSeleccionStoreRequest extends FormRequest
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
            'cotizacion_precio_id' => ['required', 'exists:costos_requisicion_cotizacion_precio,id'],
            'cantidad' => ['required', 'numeric', 'min:0.01'],
            'numero_oc' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
