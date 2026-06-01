<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class PortalFacturaPreviewRequest extends FormRequest
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
            'orden_compra_id' => ['required', 'exists:costos_ordenes_compra,id'],
            'xml' => ['required', 'file', 'mimes:xml', 'max:5120'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'notas' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'orden_compra_id.required' => 'Seleccione una orden de compra.',
            'xml.required' => 'El archivo XML del CFDI es obligatorio.',
            'xml.mimes' => 'El archivo debe ser un XML válido.',
            'pdf.mimes' => 'El archivo PDF no es válido.',
        ];
    }
}
