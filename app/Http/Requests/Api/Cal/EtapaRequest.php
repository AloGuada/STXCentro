<?php

namespace App\Http\Requests\Api\Cal;

use Illuminate\Foundation\Http\FormRequest;

class EtapaRequest extends FormRequest
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
            'obra_id' => ['required', 'exists:obras,id'],
        ];
    }
}
