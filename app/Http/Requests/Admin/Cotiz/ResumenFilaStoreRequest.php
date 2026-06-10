<?php

namespace App\Http\Requests\Admin\Cotiz;

use App\Enums\Cotiz\ResumenBloque;
use App\Enums\Cotiz\ResumenTipoFormula;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResumenFilaStoreRequest extends FormRequest
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
            'descripcion' => ['required', 'string', 'max:255'],
            'bloque' => ['required', Rule::enum(ResumenBloque::class)],
            'tipo_formula' => ['required', Rule::enum(ResumenTipoFormula::class)],
            'coef_default' => ['nullable', 'numeric'],
            'referencia_extra' => ['nullable', 'string', 'max:255'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'bloqueada' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripción es obligatoria.',
            'bloque.required' => 'El bloque es obligatorio.',
            'tipo_formula.required' => 'El tipo de fórmula es obligatorio.',
            'orden.integer' => 'El orden debe ser un número entero.',
            'orden.min' => 'El orden no puede ser negativo.',
        ];
    }
}
