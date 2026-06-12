<?php

namespace App\Http\Requests\Admin\Cotiz;

use App\Enums\Cotiz\MetodoFleteEstandar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ObraFleteEstandarUpdateRequest extends FormRequest
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
            'grupo' => ['nullable', 'string', 'max:255'],
            'metodo' => ['sometimes', Rule::enum(MetodoFleteEstandar::class)],
            'orden' => ['sometimes', 'integer', 'min:0'],
            'volumen_override' => ['nullable', 'numeric', 'min:0'],
            'kg_por_camion' => ['sometimes', 'numeric', 'min:0'],
            'pzas_por_camion' => ['sometimes', 'integer', 'min:0'],
            'ml_por_pza' => ['sometimes', 'numeric', 'gt:0'],
        ];
    }
}
