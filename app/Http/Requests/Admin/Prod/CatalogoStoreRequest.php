<?php

namespace App\Http\Requests\Admin\Prod;

use Illuminate\Foundation\Http\FormRequest;

class CatalogoStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El catálogo puede colgarse de una obra existente o dar de alta la obra en
     * el momento; en ese caso ligarla a un proyecto de cobranza es opcional.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'modo' => ['required', 'in:existente,nueva'],
            'nombre' => ['required', 'string', 'max:255'],
            'notas' => ['nullable', 'string', 'max:1000'],

            'obra_id' => ['required_if:modo,existente', 'nullable', 'exists:obras,id'],

            'obra_no' => ['required_if:modo,nueva', 'nullable', 'string', 'max:50'],
            'obra_descripcion' => ['required_if:modo,nueva', 'nullable', 'string', 'max:255'],
            'proyecto_id' => ['nullable', 'exists:proyectos,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del catálogo es obligatorio.',
            'obra_id.required_if' => 'Selecciona la obra a la que pertenece el catálogo.',
            'obra_no.required_if' => 'El número de obra es obligatorio.',
            'obra_descripcion.required_if' => 'La descripción de la obra es obligatoria.',
            'proyecto_id.exists' => 'El proyecto de cobranza seleccionado no existe.',
        ];
    }
}
