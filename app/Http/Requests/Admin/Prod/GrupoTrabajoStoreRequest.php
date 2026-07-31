<?php

namespace App\Http\Requests\Admin\Prod;

use Illuminate\Foundation\Http\FormRequest;

class GrupoTrabajoStoreRequest extends FormRequest
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
            'descripcion' => ['required', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
            'ubicacion_ids' => ['nullable', 'array'],
            'ubicacion_ids.*' => ['exists:prod_ubicaciones,id'],
            'empleados' => ['nullable', 'array'],
            'empleados.*.persona_id' => ['nullable', 'required_without:empleados.*.persona_nueva', 'exists:rh_personas,id'],
            'empleados.*.persona_nueva' => ['nullable', 'required_without:empleados.*.persona_id', 'array'],
            'empleados.*.persona_nueva.nombre' => ['required_with:empleados.*.persona_nueva', 'string', 'max:255'],
            'empleados.*.persona_nueva.apellido' => ['required_with:empleados.*.persona_nueva', 'string', 'max:255'],
            'empleados.*.categoria_empleado_id' => ['nullable', 'exists:prod_categorias_empleado,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripcion del grupo es obligatoria.',
            'empleados.*.persona_id.required_without' => 'Elige a la persona o da de alta una nueva.',
            'empleados.*.persona_nueva.nombre.required_with' => 'El nombre de la persona es obligatorio.',
            'empleados.*.persona_nueva.apellido.required_with' => 'El apellido de la persona es obligatorio.',
        ];
    }
}
