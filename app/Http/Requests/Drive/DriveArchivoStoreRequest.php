<?php

namespace App\Http\Requests\Drive;

use Illuminate\Foundation\Http\FormRequest;

class DriveArchivoStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'max:51200', 'mimes:pdf,doc,docx,xls,xlsx,zip,rar,jpg,jpeg,png,dwg,dxf'],
            'carpeta_id' => ['required', 'exists:drive_carpetas,id'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'archivo.max' => 'El archivo no puede pesar más de 50 MB.',
            'archivo.mimes' => 'Tipo de archivo no permitido. Permitidos: PDF, DOC, DOCX, XLS, XLSX, ZIP, RAR, JPG, PNG, DWG, DXF.',
        ];
    }
}
