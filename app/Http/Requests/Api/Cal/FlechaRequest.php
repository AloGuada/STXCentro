<?php

namespace App\Http\Requests\Api\Cal;

use App\Http\Requests\Api\ApiFormRequest;

class FlechaRequest extends ApiFormRequest
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
        $presencia = $this->isMethod('POST') ? 'required' : 'sometimes';

        $rules = [
            'inicio_x' => [$presencia, 'numeric'],
            'inicio_y' => [$presencia, 'numeric'],
            'fin_x' => [$presencia, 'numeric'],
            'fin_y' => [$presencia, 'numeric'],
            'esdoble' => [$presencia, 'boolean'],
            'tipo' => [$presencia, 'string', 'max:255'],
            'show_number' => [$presencia, 'boolean'],
            'pagina' => [$presencia, 'integer', 'min:1'],
            'soldador_id' => ['nullable', 'exists:cal_soldadores,id'],
        ];

        if ($this->isMethod('POST')) {
            $rules['reporte_id'] = ['required', 'exists:cal_reportes,id'];
        }

        return $rules;
    }
}
