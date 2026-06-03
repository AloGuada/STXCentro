<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;

class DocumentoCarpetaRequest extends FormRequest
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
            'parent_id' => ['nullable', 'integer', 'exists:cob_documento_carpetas,id'],
            'nombre' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'seccion_id.required' => 'La sección es obligatoria.',
            'nombre.required' => 'El nombre de la carpeta es obligatorio.',
        ];
    }
}
