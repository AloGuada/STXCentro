<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;

class SeccionMontajeStoreRequest extends FormRequest
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
            'nombre' => ['nullable', 'string', 'max:255'],
            'area_m2' => ['nullable', 'numeric', 'min:0'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
