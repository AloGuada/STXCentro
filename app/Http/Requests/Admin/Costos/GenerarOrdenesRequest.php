<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class GenerarOrdenesRequest extends FormRequest
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
            'moneda' => ['required', 'in:mxn,usd,eur'],
            'fecha_entrega_esperada' => ['nullable', 'date'],
            'notas' => ['nullable', 'string'],
            'rubros' => ['required', 'array', 'min:1'],
            'rubros.*.seleccion_id' => ['required', 'exists:costos_requisicion_seleccion,id'],
            'rubros.*.obra_rubro_id' => ['required', 'exists:costos_obra_rubros,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rubros.required' => 'Debe asignar un rubro a cada selección antes de generar las OCs.',
            'rubros.*.obra_rubro_id.required' => 'Cada selección requiere un rubro de obra.',
        ];
    }
}
