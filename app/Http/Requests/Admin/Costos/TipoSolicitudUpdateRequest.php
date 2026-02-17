<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class TipoSolicitudUpdateRequest extends FormRequest
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
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'rubros' => ['boolean'],
            'documentos' => ['nullable', 'array'],
            'documentos.*.id' => ['nullable', 'integer'],
            'documentos.*.titulo' => ['required', 'string', 'max:255'],
            'documentos.*.multiple' => ['boolean'],
            'documentos.*.texto' => ['nullable', 'string'],
            'documentos.*.texto_adicional' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'El título es obligatorio.',
            'documentos.*.titulo.required' => 'El título del documento es obligatorio.',
        ];
    }
}
