<?php

namespace App\Http\Requests\Admin\Cotiz;

use App\Enums\Cotiz\MetodoFleteEstandar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ObraFleteEstandarStoreRequest extends FormRequest
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
            'tarjeta_id' => ['required', 'exists:cotiz_tarjetas,id'],
            'grupo' => ['nullable', 'string', 'max:255'],
            'metodo' => ['nullable', Rule::enum(MetodoFleteEstandar::class)],
        ];
    }
}
