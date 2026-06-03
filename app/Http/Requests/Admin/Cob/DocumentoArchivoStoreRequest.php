<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;

class DocumentoArchivoStoreRequest extends FormRequest
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
            'seccion_id' => ['required', 'integer', 'exists:cob_documento_secciones,id'],
            'carpeta_id' => ['nullable', 'integer', 'exists:cob_documento_carpetas,id'],
            'archivos' => ['required', 'array', 'min:1'],
            'archivos.*' => ['file', 'max:51200', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivos.required' => 'Selecciona al menos un archivo.',
            'archivos.*.max' => 'Cada archivo no puede exceder 50 MB.',
            'archivos.*.mimes' => 'Solo se permiten PDF, Word, Excel, PowerPoint e imágenes (JPG/PNG).',
        ];
    }
}
