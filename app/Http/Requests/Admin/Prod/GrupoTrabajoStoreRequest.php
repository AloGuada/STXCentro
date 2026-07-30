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
            'empleados.*.nombre' => ['required_with:empleados', 'string', 'max:255'],
            'empleados.*.no_empleado' => ['nullable', 'string', 'max:50'],
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
            'empleados.*.nombre.required_with' => 'El nombre del empleado es obligatorio.',
        ];
    }
}
