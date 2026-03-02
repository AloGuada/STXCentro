<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;

class EstimacionPagoStoreRequest extends FormRequest
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
            'monto_pagado' => ['required', 'numeric', 'min:0.01'],
            'fecha_pago' => ['required', 'date'],
            'folio' => ['nullable', 'string', 'max:255'],
            'comprobante' => ['nullable', 'file', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monto_pagado.required' => 'El monto pagado es obligatorio.',
            'monto_pagado.numeric' => 'El monto pagado debe ser un número.',
            'monto_pagado.min' => 'El monto pagado debe ser mayor a 0.',
            'fecha_pago.required' => 'La fecha de pago es obligatoria.',
            'fecha_pago.date' => 'La fecha de pago debe ser una fecha válida.',
            'folio.max' => 'El folio no debe exceder 255 caracteres.',
            'comprobante.max' => 'El comprobante no debe exceder 10MB.',
        ];
    }
}
