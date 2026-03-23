<?php

namespace App\Http\Requests\Api\Cal;

use App\Http\Requests\Api\ApiFormRequest;

class SoldadorRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'certificacion' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
        ];
    }
}
