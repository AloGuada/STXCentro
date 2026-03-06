<?php

namespace App\Http\Requests\Admin\Rh;

use Illuminate\Foundation\Http\FormRequest;

class RequisicionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'puesto_id' => ['required', 'exists:rh_puestos,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'estado' => ['required', 'string', 'in:borrador,abierta,en_proceso,cerrada,cancelada'],
            'tipo_requisicion' => ['required', 'string', 'in:nueva,reemplazo,temporal'],
            'tipo_contrato_generado' => ['required', 'string', 'in:planta,obra'],
            'justificacion' => ['nullable', 'string'],
            'nombre_solicitante' => ['nullable', 'string', 'max:255'],
            'puesto_solicitante' => ['nullable', 'string', 'max:255'],
            'responsable_entrevista' => ['nullable', 'string', 'max:255'],
            'salario' => ['nullable', 'numeric', 'min:0'],
            'fecha_cierre' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'puesto_id.required' => 'El puesto es obligatorio.',
            'cantidad.required' => 'La cantidad es obligatoria.',
            'tipo_requisicion.required' => 'El tipo de requisicion es obligatorio.',
        ];
    }
}
