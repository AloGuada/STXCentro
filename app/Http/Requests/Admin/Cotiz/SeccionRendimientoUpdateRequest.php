<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;

class SeccionRendimientoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'concepto' => ['sometimes', 'required', 'string', 'max:255'],
            'largo_pza' => ['nullable', 'string', 'max:255'],
            'cantidad' => ['sometimes', 'numeric', 'min:0'],
            'rendimiento' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
