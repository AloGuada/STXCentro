<?php

namespace App\Http\Requests\Admin\Alm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AsignacionReasignarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.asignaciones.reasignar') ?? false;
    }

    /**
     * `de_obra_id` y `a_obra_id` son nullable a propósito: `null` es **lo
     * libre**, y es un origen y un destino de primera clase. Repartir material
     * sin dueño hacia una obra es la vía normal por la que el inventario que ya
     * estaba en bodega gana asignación, no un caso borde.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'existencia_id' => ['required', 'integer', 'exists:alm_existencias,id'],
            'de_obra_id' => ['nullable', 'integer', 'exists:obras,id'],
            'a_obra_id' => ['nullable', 'integer', 'exists:obras,id'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'motivo' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('de_obra_id') === $this->input('a_obra_id')) {
                $validator->errors()->add('a_obra_id', 'El origen y el destino son el mismo: no hay nada que reasignar.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cantidad.gt' => 'La cantidad a reasignar tiene que ser mayor que cero.',
            'motivo.required' => 'Escribe por qué cambia de obra: es el único rastro que queda del movimiento.',
        ];
    }
}
