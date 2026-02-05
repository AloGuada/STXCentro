<?php

namespace App\Http\Requests\Admin\Sti;

use Illuminate\Foundation\Http\FormRequest;

class MediaStoreRequest extends FormRequest
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
            'archivo' => ['required', 'file', 'image', 'max:5120'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'El archivo es obligatorio.',
            'archivo.file' => 'Debe ser un archivo válido.',
            'archivo.image' => 'El archivo debe ser una imagen.',
            'archivo.max' => 'La imagen no puede pesar más de 5MB.',
        ];
    }
}
