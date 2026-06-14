<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RubroStoreRequest extends FormRequest
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
            'codigo' => ['required', 'string', 'max:50', 'unique:costos_rubros,codigo'],
            'descripcion' => ['required', 'string', 'max:255'],
            'ambito' => ['required', Rule::in(['obra', 'planta'])],
            'ocultar_en_reporte' => ['sometimes', 'boolean'],
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
            'ambito.required' => 'El ámbito es obligatorio.',
            'ambito.in' => 'El ámbito debe ser obra o planta.',
            'tipo_rubro_id.required' => 'El tipo de centro de costos es obligatorio.',
            'tipo_rubro_id.exists' => 'El tipo de centro de costos seleccionado no existe.',
        ];
    }
}
