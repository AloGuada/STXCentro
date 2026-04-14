<?php

namespace App\Http\Requests\Admin\Dg;

use Illuminate\Foundation\Http\FormRequest;

class ReporteUpdateRequest extends FormRequest
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
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
