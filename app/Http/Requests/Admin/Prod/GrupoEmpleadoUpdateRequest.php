<?php

namespace App\Http\Requests\Admin\Prod;

use App\Models\Prod\GrupoEmpleado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edición de un integrante ya dado de alta: se le cambia la categoría con la
 * que participa en el reparto y, si es un renglón viejo sin enlace, se le
 * amarra la persona de RH que le corresponde.
 */
class GrupoEmpleadoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $empleado = $this->route('empleado');

        return [
            'categoria_empleado_id' => ['nullable', 'exists:prod_categorias_empleado,id'],
            'persona_id' => [
                'nullable',
                'exists:rh_personas,id',
                // Nadie puede estar en dos grupos a la vez.
                Rule::unique('prod_grupo_empleados', 'persona_id')
                    ->ignore($empleado instanceof GrupoEmpleado ? $empleado->id : null),
            ],
            // Se puede amarrar a alguien que todavia no existe en RH, igual que
            // en el alta: se da de alta ahi mismo con nombre y apellido.
            'persona_nueva' => ['nullable', 'array'],
            'persona_nueva.nombre' => ['required_with:persona_nueva', 'string', 'max:255'],
            'persona_nueva.apellido' => ['required_with:persona_nueva', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'persona_id.unique' => 'Esa persona ya pertenece a otro grupo de trabajo.',
            'persona_nueva.nombre.required_with' => 'El nombre de la persona es obligatorio.',
            'persona_nueva.apellido.required_with' => 'El apellido de la persona es obligatorio.',
        ];
    }
}
