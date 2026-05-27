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
            'archivo' => ['required', 'file', 'max:10240'],
            'archivo_id' => ['required', 'exists:costos_documentos,id'],
            'texto_adicional' => ['nullable', 'string', 'max:255'],
        ];
    }
}
