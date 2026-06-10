<?php

namespace App\Http\Requests\Admin\Cotiz;

use App\Enums\Cotiz\TipoCorte;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KilosRealesCategoriaStoreRequest extends FormRequest
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
            'descripcion' => ['required', 'string', 'max:255', 'unique:cotiz_kilos_reales_categorias,descripcion'],
            'tipo_corte' => ['required', Rule::enum(TipoCorte::class)],
            'orden' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripción es obligatoria.',
            'descripcion.unique' => 'Esta descripción ya está registrada.',
            'tipo_corte.required' => 'El tipo de corte es obligatorio.',
            'orden.integer' => 'El orden debe ser un número entero.',
            'orden.min' => 'El orden no puede ser negativo.',
        ];
    }
}
