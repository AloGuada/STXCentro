<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class RequisicionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('costos.requisiciones.crear') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'departamento_id' => ['required', 'exists:departamentos,id'],
            'concepto' => ['required', 'string', 'max:255'],
            'justificacion' => ['nullable', 'string'],
            'fecha_requerida' => ['nullable', 'date'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id' => ['nullable', 'integer'],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.unidad' => ['required', 'string', 'max:20'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.notas' => ['nullable', 'string'],
            '_version' => ['nullable', 'string'],
        ];
    }
}
