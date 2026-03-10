<?php

namespace App\Http\Requests\Admin\Drive;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExternoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('drive_externos', 'email')->ignore($this->route('externo'))],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'telefono' => ['nullable', 'string', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
