<?php

namespace App\Http\Requests\Admin\Prod;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de un integrante en un grupo de trabajo. Se elige una persona ya
 * existente en RH o se da de alta una nueva ahí mismo (basta nombre y apellido:
 * el resto del expediente lo llena RH cuando la contrate).
 */
class GrupoEmpleadoStoreRequest extends FormRequest
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
            'persona_id' => [
                'nullable',
                'required_without:persona_nueva',
                'exists:rh_personas,id',
                // Nadie puede estar en dos grupos a la vez.
                Rule::unique('prod_grupo_empleados', 'persona_id'),
            ],
            'persona_nueva' => ['nullable', 'required_without:persona_id', 'array'],
            'persona_nueva.nombre' => ['required_with:persona_nueva', 'string', 'max:255'],
            'persona_nueva.apellido' => ['required_with:persona_nueva', 'string', 'max:255'],
            'categoria_empleado_id' => ['nullable', 'exists:prod_categorias_empleado,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'persona_id.required_without' => 'Elige a la persona o da de alta una nueva.',
            'persona_id.unique' => 'Esa persona ya pertenece a otro grupo de trabajo.',
            'persona_nueva.nombre.required_with' => 'El nombre de la persona es obligatorio.',
            'persona_nueva.apellido.required_with' => 'El apellido de la persona es obligatorio.',
        ];
    }
}
