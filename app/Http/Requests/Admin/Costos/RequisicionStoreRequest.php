<?php

namespace App\Http\Requests\Admin\Costos;

use App\Models\Costos\ObraRubro;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequisicionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('costos.requisiciones.crear') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'departamento_id' => ['required', 'exists:departamentos,id'],
            // Sin presupuesto = requisición multipresupuesto: cada partida define
            // el suyo vía el centro de costos (obra_rubro), sin candado único.
            'presupuesto_id' => ['nullable', 'exists:costos_presupuestos,id'],
            'justificacion' => ['nullable', 'string'],
            'fecha_requerida' => ['nullable', 'date'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['nullable', 'exists:costos_productos,id'],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.unidad' => ['required', 'string', 'max:20'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.obra_rubro_id' => ['required', 'exists:costos_obra_rubros,id'],
            'detalles.*.uso_cfdi_id' => ['required', Rule::exists('costos_usos_cfdi', 'id')->where('activo', true)],
            'detalles.*.notas' => ['nullable', 'string'],
            'documentos' => ['nullable', 'array'],
            'documentos.*' => ['file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Solo se valida la pertenencia cuando la requisición es de un solo
            // presupuesto (multipresupuesto no tiene cabecera).
            if ($this->filled('presupuesto_id')) {
                ObraRubro::validarPertenenciaPresupuesto($validator, (int) $this->integer('presupuesto_id'), (array) $this->input('detalles', []));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'presupuesto_id.required' => 'Debe seleccionar el presupuesto de la requisición.',
            'detalles.required' => 'Debe registrar al menos una partida.',
            'detalles.min' => 'Debe registrar al menos una partida.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a cero.',
            'detalles.*.obra_rubro_id.required' => 'Cada partida requiere un centro de costos.',
            'detalles.*.uso_cfdi_id.required' => 'Cada partida requiere un uso de CFDI.',
            'detalles.*.uso_cfdi_id.exists' => 'El uso de CFDI seleccionado no es válido o está inactivo.',
            'documentos.*.mimes' => 'Los documentos deben ser archivos PDF.',
            'documentos.*.max' => 'Cada documento no debe superar 10 MB.',
        ];
    }
}
