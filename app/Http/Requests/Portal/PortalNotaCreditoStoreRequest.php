<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class PortalNotaCreditoStoreRequest extends FormRequest
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
            'factura_id' => ['required', 'exists:costos_facturas,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'concepto' => ['required', 'string', 'max:500'],
            'fecha_emision' => ['required', 'date'],
            'uuid_fiscal' => ['nullable', 'string', 'max:255', 'unique:costos_notas_credito,uuid_fiscal'],
            'folio_fiscal' => ['nullable', 'string', 'max:255'],
            'xml' => ['nullable', 'file', 'mimes:xml,txt', 'max:5120'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monto.min' => 'El monto debe ser mayor a cero.',
            'uuid_fiscal.unique' => 'Ya existe una nota de crédito con este UUID fiscal.',
        ];
    }
}
