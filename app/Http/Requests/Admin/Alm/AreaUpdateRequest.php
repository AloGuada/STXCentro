<?php

namespace App\Http\Requests\Admin\Alm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AreaUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.areas.editar') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'descripcion' => [
                'required', 'string', 'max:255',
                Rule::unique('alm_areas', 'descripcion')->ignore($this->route('area')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripción del área es obligatoria.',
            'descripcion.unique' => 'Ya existe un área con esa descripción.',
        ];
    }
}
