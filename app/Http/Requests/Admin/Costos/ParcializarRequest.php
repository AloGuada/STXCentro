<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class ParcializarRequest extends FormRequest
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
        return [
            'parcialidades' => ['required', 'array', 'min:2'],
            'parcialidades.*.monto' => ['required', 'numeric', 'min:0.01'],
            'parcialidades.*.fecha_programada' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parcialidades.min' => 'Debe haber al menos 2 parcialidades.',
            'parcialidades.*.monto.required' => 'El monto de cada parcialidad es obligatorio.',
            'parcialidades.*.monto.min' => 'El monto debe ser mayor a 0.',
            'parcialidades.*.fecha_programada.required' => 'La fecha de cada parcialidad es obligatoria.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $pago = $this->route('pago');

            if ($pago->tieneParcialidades()) {
                $validator->errors()->add('parcialidades', 'Este pago ya fue parcializado.');

                return;
            }

            $parcialidades = $this->input('parcialidades', []);
            $suma = collect($parcialidades)->sum('monto');

            if (round($suma, 2) !== round((float) $pago->monto_pago, 2)) {
                $validator->errors()->add('parcialidades', "La suma de parcialidades ({$suma}) debe ser igual al monto del pago ({$pago->monto_pago}).");
            }
        });
    }
}
