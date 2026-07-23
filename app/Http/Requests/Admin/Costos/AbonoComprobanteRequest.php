<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class AbonoComprobanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $pago = $this->route('pago');
        $esDivisa = $pago instanceof \App\Models\Costos\Pago && $pago->moneda !== 'mxn';

        return [
            'comprobante' => ['required', 'file', 'max:10240'],
            'notas' => ['nullable', 'string', 'max:500'],
            // El monto realmente pagado en MXN se captura solo para pagos en
            // divisa; reconcilia el presupuesto contra el TC efectivo del banco.
            'monto_real_mxn' => [$esDivisa ? 'required' : 'nullable', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monto_real_mxn.required' => 'Captura el monto real pagado en MXN para conciliar el presupuesto.',
        ];
    }
}
