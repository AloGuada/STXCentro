<?php

namespace App\Http\Requests\Admin\Qal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El IFC de una obra, tal como lo exporta Tekla. Pesa decenas de MB: el límite
 * de 200 MB deja margen para una nave completa.
 */
class ModeloIfcRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'obra_id' => ['required', 'integer', 'exists:obras,id'],
            'archivo' => ['required', 'file', 'extensions:ifc', 'max:204800'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'obra_id.required' => 'Falta la obra del modelo.',
            'archivo.required' => 'Falta el archivo IFC.',
            'archivo.extensions' => 'El modelo tiene que ser un archivo .ifc exportado de Tekla.',
            'archivo.max' => 'El IFC no puede pesar más de 200 MB.',
        ];
    }
}
