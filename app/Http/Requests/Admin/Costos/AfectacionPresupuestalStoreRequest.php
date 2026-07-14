<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class AfectacionPresupuestalStoreRequest extends FormRequest
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
            'fecha' => ['required', 'date'],
            'tipo_origen' => ['required', 'string', 'in:nomina,gasto_directo,reembolso,ajuste_presupuestal,otro'],
            'descripcion' => ['required', 'string'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.obra_rubro_id' => ['required', 'exists:costos_obra_rubros,id'],
            'detalles.*.monto' => ['required', 'numeric', 'min:0.01'],
            'documentos' => ['nullable', 'array'],
            'documentos.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es obligatoria.',
            'tipo_origen.required' => 'El tipo de origen es obligatorio.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'detalles.required' => 'Agrega al menos un centro de costos a afectar.',
            'detalles.min' => 'Agrega al menos un centro de costos a afectar.',
            'detalles.*.obra_rubro_id.required' => 'El centro de costos es obligatorio.',
            'detalles.*.monto.required' => 'El monto a afectar es obligatorio.',
            'detalles.*.monto.min' => 'El monto a afectar debe ser mayor a 0.',
            'documentos.*.mimes' => 'Los documentos deben ser PDF o imagen (jpg, png).',
            'documentos.*.max' => 'Cada documento no debe superar 10 MB.',
        ];
    }
}
