<?php

namespace App\Http\Requests\Admin\Alm;

use App\Enums\Alm\DocumentoAlm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FirmasDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.aprobaciones.configurar') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'documentos' => ['present', 'array'],
            'documentos.*.documento' => ['required', Rule::enum(DocumentoAlm::class)],
            'documentos.*.firmas' => ['present', 'array', 'max:6'],
            'documentos.*.firmas.*.rotulo' => ['required', 'string', 'max:60'],
            'documentos.*.firmas.*.nombre' => ['nullable', 'string', 'max:60'],
            'documentos.*.firmas.*.usuarios' => ['present', 'array', 'max:5'],
            'documentos.*.firmas.*.usuarios.*' => ['uuid', Rule::exists('usuarios', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'documentos.*.firmas.max' => 'Un formato no lleva más de 6 firmas: no caben en la hoja.',
            'documentos.*.firmas.*.rotulo.required' => 'Cada raya necesita decir de qué es la firma.',
            'documentos.*.firmas.*.rotulo.max' => 'El rótulo de la firma no puede pasar de 60 caracteres.',
            'documentos.*.firmas.*.usuarios.max' => 'Máximo 5 usuarios por firma: más no caben sobre la raya.',
            'documentos.*.firmas.*.nombre.max' => 'El nombre sobre la raya no puede pasar de 60 caracteres.',
        ];
    }
}
