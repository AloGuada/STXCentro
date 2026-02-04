<?php

namespace App\Http\Requests\Admin\Intra;

use Illuminate\Foundation\Http\FormRequest;

class SeccionEstaticaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'boton' => ['required', 'string', 'max:255'],
            'activo' => ['boolean'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'El título es obligatorio.',
            'boton.required' => 'El texto del botón es obligatorio.',
            'file.required' => 'El archivo PDF es obligatorio.',
            'file.mimes' => 'El archivo debe ser un PDF.',
            'file.max' => 'El archivo no puede superar 20MB.',
        ];
    }
}
