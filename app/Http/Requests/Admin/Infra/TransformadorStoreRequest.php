<?php

namespace App\Http\Requests\Admin\Infra;

use Illuminate\Foundation\Http\FormRequest;

class TransformadorStoreRequest extends FormRequest
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
            'linea_A' => ['nullable', 'numeric', 'min:0'],
            'linea_A_max' => ['nullable', 'numeric', 'min:0'],
            'date_A' => ['nullable', 'date'],
            'linea_B' => ['nullable', 'numeric', 'min:0'],
            'linea_B_max' => ['nullable', 'numeric', 'min:0'],
            'date_B' => ['nullable', 'date'],
            'linea_C' => ['nullable', 'numeric', 'min:0'],
            'linea_C_max' => ['nullable', 'numeric', 'min:0'],
            'date_C' => ['nullable', 'date'],
            'voltaje_a' => ['nullable', 'numeric', 'min:0'],
            'voltaje_b' => ['nullable', 'numeric', 'min:0'],
            'voltaje_c' => ['nullable', 'numeric', 'min:0'],
            'total_1' => ['nullable', 'numeric', 'min:0'],
            'total_5' => ['nullable', 'numeric', 'min:0'],
            'lectura_5y5' => ['nullable', 'numeric', 'min:0'],
            'registro_a' => ['nullable', 'numeric', 'min:0'],
            'registro_b' => ['nullable', 'numeric', 'min:0'],
            'registro_c' => ['nullable', 'numeric', 'min:0'],
            'tarifa' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string'],
            'infra_turno_id' => ['nullable', 'integer', 'exists:infra_turnos,id'],
            'fecha' => ['nullable', 'date'],
        ];
    }
}
