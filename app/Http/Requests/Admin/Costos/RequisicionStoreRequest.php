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
            'firma_adicional_aprobador_id' => ['nullable', 'uuid', 'exists:usuarios,id'],
            // Requisición "sin obra": no carga a ningún centro de costos, así que
            // ni lleva presupuesto de cabecera ni obra_rubro por partida.
            'sin_centro_costos' => ['boolean'],
            // Sin presupuesto = requisición multipresupuesto: cada partida define
            // el suyo vía el centro de costos (obra_rubro), sin candado único.
            'presupuesto_id' => [Rule::prohibitedIf($this->sinCentroCostos()), 'nullable', 'exists:costos_presupuestos,id'],
            'justificacion' => ['nullable', 'string'],
            'fecha_requerida' => ['nullable', 'date'],
            'detalles' => ['required', 'array', 'min:1'],
            // Del catálogo, siempre: la partida tecleada al vuelo ya no estrena
            // producto. Lo que no existe se da de alta en Almacén > Artículos.
            'detalles.*.producto_id' => ['required', 'exists:costos_productos,id'],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.unidad' => ['required', 'string', 'max:20'],
            'detalles.*.cantidad' => ['required', 'numeric', 'decimal:0,4', 'min:0.0001'],
            'detalles.*.obra_rubro_id' => $this->sinCentroCostos()
                ? ['prohibited']
                : ['required', 'exists:costos_obra_rubros,id'],
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
            'presupuesto_id.prohibited' => 'Una requisición sin obra no lleva presupuesto.',
            'detalles.*.obra_rubro_id.prohibited' => 'Una requisición sin obra no lleva centro de costos en sus partidas.',
            'detalles.required' => 'Debe registrar al menos una partida.',
            'detalles.min' => 'Debe registrar al menos una partida.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a cero.',
            'detalles.*.cantidad.decimal' => 'La cantidad admite hasta 4 decimales.',
            'detalles.*.producto_id.required' => 'Esa partida no está en el catálogo. Un producto nuevo se da de alta en Almacén → Artículos.',
            'detalles.*.producto_id.exists' => 'Ese producto ya no existe en el catálogo.',
            'detalles.*.obra_rubro_id.required' => 'Cada partida requiere un centro de costos.',
            'detalles.*.uso_cfdi_id.required' => 'Cada partida requiere un uso de CFDI.',
            'detalles.*.uso_cfdi_id.exists' => 'El uso de CFDI seleccionado no es válido o está inactivo.',
            'documentos.*.mimes' => 'Los documentos deben ser archivos PDF.',
            'documentos.*.max' => 'Cada documento no debe superar 10 MB.',
        ];
    }
}
