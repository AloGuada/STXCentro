<?php

namespace App\Http\Requests\Api\Cal;

use App\Http\Requests\Api\ApiFormRequest;

class PiezaRequest extends ApiFormRequest
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
            'marca' => ['required', 'string', 'max:255'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'etapa_id' => ['required', 'exists:cal_etapas,id'],
        ];
    }
}
