<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class RecepcionesReporteRequest extends FormRequest
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
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'search' => ['nullable', 'string', 'max:255'],
            'tipo' => ['nullable', 'in:parcial,completa'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_inicio.required' => 'Indica la fecha inicial del rango.',
            'fecha_fin.required' => 'Indica la fecha final del rango.',
            'fecha_fin.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ];
    }
}
