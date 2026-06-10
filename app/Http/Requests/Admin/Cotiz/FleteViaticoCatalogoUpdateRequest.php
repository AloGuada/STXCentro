<?php

namespace App\Http\Requests\Admin\Cotiz;

use App\Enums\Cotiz\GrupoFlete;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FleteViaticoCatalogoUpdateRequest extends FormRequest
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
            'grupo' => ['required', Rule::enum(GrupoFlete::class)],
            'orden' => ['nullable', 'integer', 'min:0'],
            'concepto' => ['required', 'string', 'max:255'],
            'unidad' => ['nullable', 'string', 'max:50'],
            'p_unit_default' => ['required', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:500'],
            'clave' => ['nullable', 'string', 'max:100'],
            'formula_cantidad' => ['nullable', 'string', 'max:500'],
            'formula_p_unit' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grupo.required' => 'El grupo es obligatorio.',
            'concepto.required' => 'El concepto es obligatorio.',
            'p_unit_default.required' => 'El precio unitario por defecto es obligatorio.',
            'p_unit_default.min' => 'El precio unitario por defecto no puede ser negativo.',
            'orden.integer' => 'El orden debe ser un número entero.',
            'orden.min' => 'El orden no puede ser negativo.',
        ];
    }
}
