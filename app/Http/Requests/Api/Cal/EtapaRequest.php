<?php

namespace App\Http\Requests\Api\Cal;

use App\Http\Requests\Api\ApiFormRequest;

class EtapaRequest extends ApiFormRequest
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
            'descripcion' => ['required', 'string', 'max:255'],
            'obra_id' => ['required', 'exists:cal_obras,id'],
        ];
    }
}
