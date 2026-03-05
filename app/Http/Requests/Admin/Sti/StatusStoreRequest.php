<?php

namespace App\Http\Requests\Admin\Sti;

use Illuminate\Foundation\Http\FormRequest;

class StatusStoreRequest extends FormRequest
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
            'orden' => ['nullable', 'integer', 'min:0'],
            'detiene_tiempo' => ['boolean'],
            'color' => ['required', 'string', 'max:7'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripción del estado es obligatoria.',
        ];
    }
}
