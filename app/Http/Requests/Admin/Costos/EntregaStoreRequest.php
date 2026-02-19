<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class EntregaStoreRequest extends FormRequest
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
            'fecha_entrega' => ['required', 'date'],
            'observaciones' => ['nullable', 'string'],
            'archivo' => ['nullable', 'file', 'max:10240'],
        ];
    }
}
