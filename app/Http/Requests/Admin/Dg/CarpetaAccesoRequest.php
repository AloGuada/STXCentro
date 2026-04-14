<?php

namespace App\Http\Requests\Admin\Dg;

use Illuminate\Foundation\Http\FormRequest;

class CarpetaAccesoRequest extends FormRequest
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
            'usuario_id' => ['required', 'uuid', 'exists:usuarios,id'],
            'puede_escribir' => ['required', 'boolean'],
        ];
    }
}
