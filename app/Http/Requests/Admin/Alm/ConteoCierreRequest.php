<?php

namespace App\Http\Requests\Admin\Alm;

use Illuminate\Foundation\Http\FormRequest;

class ConteoCierreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.conteos.cerrar') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
