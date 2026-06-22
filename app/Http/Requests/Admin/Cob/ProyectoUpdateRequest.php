<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;

class ProyectoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'descripcion' => ['required', 'string', 'max:255'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
        ];
    }
}
