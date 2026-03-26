<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BadgeConfigUpdateRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
            'tabla' => ['required', 'string', 'max:100'],
            'campo_estatus' => ['required', 'string', 'max:100'],
            'operador' => ['required', 'string', 'in:=,!=,>,>=,<,<=,like'],
            'valor_estatus' => ['required', 'string', 'max:255'],
            'condiciones_extra' => ['nullable', 'json'],
            'rol' => ['required', 'string', 'max:100'],
            'nav_href' => ['required', 'string', 'max:255'],
            'filter_href' => ['nullable', 'string', 'max:255'],
            'activo' => ['boolean'],
        ];
    }
}
