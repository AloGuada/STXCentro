<?php

namespace App\Http\Requests\Admin\Dg;

use Illuminate\Foundation\Http\FormRequest;

class ReporteStoreRequest extends FormRequest
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
            'carpeta_id' => ['required', 'integer', 'exists:dg_carpetas,id'],
            'anio' => ['required', 'integer', 'min:2020', 'max:2099'],
            'semana' => ['required', 'integer', 'min:1', 'max:53'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'archivos' => ['nullable', 'array'],
            'archivos.*' => ['file', 'max:51200', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivos.*.max' => 'Cada archivo no puede exceder 50 MB.',
            'archivos.*.mimes' => 'Solo se permiten archivos PDF, Word, Excel y PowerPoint.',
        ];
    }
}
