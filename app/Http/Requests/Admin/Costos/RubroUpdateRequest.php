<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RubroUpdateRequest extends FormRequest
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
        return [
            'codigo' => ['required', 'string', 'max:50', Rule::unique('costos_rubros', 'codigo')->ignore($this->route('rubro'))],
            'descripcion' => ['required', 'string', 'max:255'],
            'tipo_rubro_id' => ['required', 'exists:costos_tipo_rubros,id'],
            'departamento_id' => ['nullable', 'exists:departamentos,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique' => 'Este código ya está registrado.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'tipo_rubro_id.required' => 'El tipo de rubro es obligatorio.',
            'tipo_rubro_id.exists' => 'El tipo de rubro seleccionado no existe.',
        ];
    }
}
