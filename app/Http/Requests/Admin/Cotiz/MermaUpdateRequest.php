<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MermaUpdateRequest extends FormRequest
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
            'descripcion' => ['required', 'string', 'max:255', Rule::unique('cotiz_mermas', 'descripcion')->ignore($this->route('merma'))],
            'formula' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripción es obligatoria.',
            'descripcion.unique' => 'Esta descripción ya está registrada.',
            'formula.required' => 'La fórmula es obligatoria.',
        ];
    }
}
