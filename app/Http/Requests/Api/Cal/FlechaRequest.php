<?php

namespace App\Http\Requests\Api\Cal;

use Illuminate\Foundation\Http\FormRequest;

class FlechaRequest extends FormRequest
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
            'reporte_id' => ['required', 'exists:cal_reportes,id'],
            'inicio_x' => ['required', 'numeric'],
            'inicio_y' => ['required', 'numeric'],
            'fin_x' => ['required', 'numeric'],
            'fin_y' => ['required', 'numeric'],
            'esdoble' => ['nullable', 'boolean'],
            'tipo' => ['nullable', 'string', 'max:255'],
            'show_number' => ['nullable', 'boolean'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
