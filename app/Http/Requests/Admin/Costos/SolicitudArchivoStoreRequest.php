<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class SolicitudArchivoStoreRequest extends FormRequest
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
            'archivo' => ['required', 'file', 'max:15360'],
            'archivo_id' => ['required', 'exists:costos_documentos,id'],
            'texto_adicional' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'Selecciona un archivo para subir.',
            'archivo.file' => 'El documento adjunto no es un archivo válido.',
            'archivo.max' => 'El archivo no puede superar los 10 MB.',
        ];
    }
}
