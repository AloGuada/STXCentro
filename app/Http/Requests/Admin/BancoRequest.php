<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BancoRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255', Rule::unique('bancos', 'nombre')->ignore($this->route('banco'))],
            'digitos_cuenta' => [$this->boolean('es_pagador') ? 'required' : 'nullable', 'integer', 'min:1', 'max:30'],
            'es_pagador' => ['boolean'],
            'activo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del banco es obligatorio.',
            'nombre.unique' => 'Este banco ya está registrado.',
            'digitos_cuenta.required' => 'Indique la longitud del número de cuenta del banco pagador.',
        ];
    }
}
