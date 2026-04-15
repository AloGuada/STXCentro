<?php

namespace App\Http\Requests\Admin\Dg;

use Illuminate\Foundation\Http\FormRequest;

class NotaRequest extends FormRequest
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
            'titulo' => ['nullable', 'string', 'max:255'],
            'contenido' => ['nullable', 'string', 'max:200000'],
        ];
    }
}
