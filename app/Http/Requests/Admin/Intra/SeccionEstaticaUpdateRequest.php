<?php

namespace App\Http\Requests\Admin\Intra;

use Illuminate\Foundation\Http\FormRequest;

class SeccionEstaticaUpdateRequest extends FormRequest
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
            'url_externa' => ['nullable', 'url', 'max:2048'],
            'activo' => ['boolean'],
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
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
            'url_externa.url' => 'La URL externa debe ser válida.',
            'file.mimes' => 'El archivo debe ser un PDF.',
            'file.max' => 'El archivo no puede superar 20MB.',
        ];
    }
}
