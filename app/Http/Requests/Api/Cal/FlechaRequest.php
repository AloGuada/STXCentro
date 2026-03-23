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
        $rules = [
            'inicio_x' => ['required', 'numeric'],
            'inicio_y' => ['required', 'numeric'],
            'fin_x' => ['required', 'numeric'],
            'fin_y' => ['required', 'numeric'],
            'esdoble' => ['required', 'boolean'],
            'tipo' => ['required', 'string', 'max:255'],
            'show_number' => ['required', 'boolean'],
            'pagina' => ['required', 'integer', 'min:1'],
            'soldador_id' => ['nullable', 'exists:cal_soldadores,id'],
        ];

        if ($this->isMethod('POST')) {
            $rules['reporte_id'] = ['required', 'exists:cal_reportes,id'];
        }

        return $rules;
    }
}
