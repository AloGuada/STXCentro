<?php

namespace App\Http\Requests\Admin\Intra;

use App\Enums\TipoDocumento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentoUpdateRequest extends FormRequest
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
            'area_id' => ['required', 'integer', 'exists:intra_area,id'],
            'descripcion' => ['required', 'string', 'max:255'],
            'codigo' => ['nullable', 'string', 'max:50'],
            'tipo' => ['required', 'string', Rule::enum(TipoDocumento::class)],
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
            'area_id.required' => 'El área es obligatoria.',
            'area_id.exists' => 'El área seleccionada no existe.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'tipo.required' => 'El tipo de documento es obligatorio.',
            'tipo.enum' => 'El tipo de documento no es válido.',
            'file.mimes' => 'El archivo debe ser un PDF.',
            'file.max' => 'El archivo no puede superar 20MB.',
        ];
    }
}
