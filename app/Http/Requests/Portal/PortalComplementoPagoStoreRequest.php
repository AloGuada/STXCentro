<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class PortalComplementoPagoStoreRequest extends FormRequest
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
            'xml' => ['required', 'file', 'mimes:xml,txt', 'max:5120'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'xml.required' => 'Debe adjuntar el XML del complemento de pago.',
        ];
    }
}
