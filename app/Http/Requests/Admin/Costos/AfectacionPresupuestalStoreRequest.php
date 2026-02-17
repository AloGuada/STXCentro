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
            'departamento_id' => ['required', 'exists:departamentos,id'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'detalles' => ['nullable', 'array'],
            'detalles.*.obra_rubro_id' => ['required', 'exists:costos_obra_rubros,id'],
            'detalles.*.concepto' => ['required', 'string', 'max:255'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
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
            'departamento_id.required' => 'El departamento es obligatorio.',
        ];
    }
}
