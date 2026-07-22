<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class ReasignarCentroCostosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('costos.centros-costos.reasignar') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.obra_rubro_id' => ['required', 'integer', 'exists:costos_obra_rubros,id'],
            'detalles.*.monto' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.concepto' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Indique el motivo de la reasignación.',
            'motivo.min' => 'El motivo debe tener al menos 10 caracteres.',
            'detalles.required' => 'Agregue al menos un centro de costos.',
            'detalles.min' => 'Agregue al menos un centro de costos.',
            'detalles.*.obra_rubro_id.required' => 'Seleccione el centro de costos.',
            'detalles.*.monto.min' => 'El monto debe ser mayor a cero.',
        ];
    }
}
