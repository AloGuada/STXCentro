<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;

class AnticipoUpdateRequest extends FormRequest
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
            'folio' => ['nullable', 'string', 'max:255'],
            'fecha_emision' => ['nullable', 'date'],
            'monto' => ['required', 'numeric', 'min:0'],
            'moneda' => ['required', 'string', 'max:3'],
            'estado' => ['required', 'string', 'in:pendiente,aplicado,devuelto'],
            'fecha_pagado' => ['nullable', 'date'],
            'comentarios' => ['nullable', 'string'],
            'comprobante' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'folio.max' => 'El folio no debe exceder 255 caracteres.',
            'monto.required' => 'El monto es obligatorio.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.min' => 'El monto debe ser mayor o igual a 0.',
            'moneda.required' => 'La moneda es obligatoria.',
            'moneda.max' => 'La moneda no debe exceder 3 caracteres.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado debe ser pendiente, aplicado o devuelto.',
        ];
    }
}
