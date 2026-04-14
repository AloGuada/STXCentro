<?php

namespace App\Http\Requests\Admin\Dg;

use Illuminate\Foundation\Http\FormRequest;

class ReporteArchivoStoreRequest extends FormRequest
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
            'archivos' => ['required', 'array', 'min:1'],
            'archivos.*' => ['file', 'max:51200', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx'],
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
            'archivos.*.mimes' => 'Solo se permiten archivos PDF, Word, Excel y PowerPoint.',
        ];
    }
}
