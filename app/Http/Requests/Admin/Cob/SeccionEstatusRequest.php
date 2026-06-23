<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SeccionEstatusRequest extends FormRequest
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
            'estatus' => ['required', Rule::in(['pendiente', 'completado'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estatus.required' => 'El estatus es obligatorio.',
            'estatus.in' => 'El estatus debe ser pendiente o completado.',
        ];
    }
}
