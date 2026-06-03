<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentoSeccionRequest extends FormRequest
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
        $id = $this->route('documentoSeccion')?->id;

        return [
            'nombre' => ['required', 'string', 'max:255', Rule::unique('cob_documento_secciones', 'nombre')->ignore($id)],
            'orden' => ['nullable', 'integer', 'min:0', 'max:999'],
            'activo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la sección es obligatorio.',
            'nombre.unique' => 'Ya existe una sección con ese nombre.',
        ];
    }
}
