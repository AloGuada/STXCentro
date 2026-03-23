<?php

namespace App\Http\Requests\Api\Cal;

use App\Http\Requests\Api\ApiFormRequest;

class PiezaPlanoRequest extends ApiFormRequest
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
            'pieza_id' => ['required', 'exists:cal_piezas,id'],
            'version' => ['required', 'integer', 'min:1'],
        ];

        if ($this->isMethod('POST')) {
            $rules['pdf'] = ['required', 'file', 'mimes:pdf', 'max:10240'];
            $rules['plano_normal'] = ['nullable', 'file', 'max:10240'];
            $rules['dwg'] = ['nullable', 'file', 'max:10240'];
        }

        return $rules;
    }
}
