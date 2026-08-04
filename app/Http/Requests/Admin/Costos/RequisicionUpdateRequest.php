<?php

namespace App\Http\Requests\Admin\Costos;

use App\Models\Costos\ObraRubro;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequisicionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('costos.requisiciones.crear') ?? false;
    }

    /**
     * Requisición marcada "sin obra": no impacta ningún centro de costos.
     */
    public function sinCentroCostos(): bool
    {
        return $this->boolean('sin_centro_costos');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'departamento_id' => ['required', 'exists:departamentos,id'],
            // Requisición "sin obra": ni presupuesto de cabecera ni obra_rubro por partida.
            'sin_centro_costos' => ['boolean'],
            // Sin presupuesto = requisición multipresupuesto (cada partida define el suyo).
            'presupuesto_id' => [Rule::prohibitedIf($this->sinCentroCostos()), 'nullable', 'exists:costos_presupuestos,id'],
            'justificacion' => ['nullable', 'string'],
            'fecha_requerida' => ['nullable', 'date'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id' => ['nullable', 'integer'],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.unidad' => ['required', 'string', 'max:20'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.obra_rubro_id' => $this->sinCentroCostos()
                ? ['prohibited']
                : ['required', 'exists:costos_obra_rubros,id'],
            'detalles.*.uso_cfdi_id' => ['required', Rule::exists('costos_usos_cfdi', 'id')->where('activo', true)],
            'detalles.*.notas' => ['nullable', 'string'],
            '_version' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
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
            'presupuesto_id.prohibited' => 'Una requisición sin obra no lleva presupuesto.',
            'detalles.*.obra_rubro_id.prohibited' => 'Una requisición sin obra no lleva centro de costos en sus partidas.',
            'detalles.*.obra_rubro_id.required' => 'Cada partida requiere un centro de costos.',
            'detalles.*.uso_cfdi_id.required' => 'Cada partida requiere un uso de CFDI.',
            'detalles.*.uso_cfdi_id.exists' => 'El uso de CFDI seleccionado no es válido o está inactivo.',
        ];
    }
}
