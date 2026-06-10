<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;

class FaseMontajeStoreRequest extends FormRequest
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
            'codigo' => ['required', 'string', 'max:100', 'unique:cotiz_fases_montaje,codigo'],
            'nombre' => ['required', 'string', 'max:255'],
            'unidad' => ['required', 'string', 'max:20'],
            'centro_costo_id' => ['nullable', 'exists:cotiz_centros_costos,id'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique' => 'Este código ya está registrado.',
            'nombre.required' => 'El nombre es obligatorio.',
            'unidad.required' => 'La unidad es obligatoria.',
            'centro_costo_id.exists' => 'El centro de costo seleccionado no existe.',
            'orden.integer' => 'El orden debe ser un número entero.',
            'orden.min' => 'El orden no puede ser negativo.',
        ];
    }
}
