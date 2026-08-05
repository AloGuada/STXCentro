<?php

namespace App\Http\Requests\Admin\Prod;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcesoStoreRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255', Rule::unique('prod_procesos', 'nombre')],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['nullable', 'boolean'],
            'eventos' => ['nullable', 'array'],
            // El evento es unico en todo el catalogo: si el mismo numero pagara
            // dos procesos, un movimiento se cargaria a los dos.
            'eventos.*.evento' => ['required', 'string', 'max:20', Rule::unique('prod_proceso_eventos', 'evento')],
            'eventos.*.descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.unique' => 'Ya existe un proceso con ese nombre.',
            'eventos.*.evento.required' => 'El número de evento es obligatorio.',
            'eventos.*.evento.unique' => 'Ese evento ya está asignado a otro proceso.',
        ];
    }
}
