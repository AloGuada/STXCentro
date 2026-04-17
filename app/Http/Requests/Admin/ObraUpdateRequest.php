<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ObraUpdateRequest extends FormRequest
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
            'no' => ['required', 'string', 'max:50'],
            'descripcion' => ['required', 'string', 'max:255'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'presupuesto_total' => ['nullable', 'numeric', 'min:0'],
            'ingreso_real' => ['nullable', 'numeric', 'min:0'],
            'estatus' => ['nullable', 'string', 'in:planificacion,en_proceso,activa,suspendida,completada,cancelada'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'no.required' => 'El número de obra es obligatorio.',
            'descripcion.required' => 'La descripción es obligatoria.',
        ];
    }
}
