<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;

class SeccionRendimientoStoreRequest extends FormRequest
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
            'fase_id' => ['required', 'exists:cotiz_fases_montaje,id'],
            'concepto' => ['nullable', 'string', 'max:255'],
        ];
    }
}
