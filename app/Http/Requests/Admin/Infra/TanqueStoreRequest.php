<?php

namespace App\Http\Requests\Admin\Infra;

use Illuminate\Foundation\Http\FormRequest;

class TanqueStoreRequest extends FormRequest
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
            'pa_sistema_oxigeno' => ['nullable', 'numeric', 'min:0'],
            'presion_sistema_oxigeno' => ['nullable', 'numeric', 'min:0'],
            'presion_tanque_oxigeno' => ['nullable', 'numeric', 'min:0'],
            'lt_tanque_oxigeno' => ['nullable', 'numeric', 'min:0'],
            'kg_tanque_oxigeno' => ['nullable', 'numeric', 'min:0'],
            'pa_sistema_argon' => ['nullable', 'numeric', 'min:0'],
            'presion_sistema_argon' => ['nullable', 'numeric', 'min:0'],
            'presion_tanque_argon' => ['nullable', 'numeric', 'min:0'],
            'lt_tanque_argon' => ['nullable', 'numeric', 'min:0'],
            'kg_tanque_argon' => ['nullable', 'numeric', 'min:0'],
            'pa_sistema_co2' => ['nullable', 'numeric', 'min:0'],
            'presion_sistema_co2' => ['nullable', 'numeric', 'min:0'],
            'presion_tanque_co2' => ['nullable', 'numeric', 'min:0'],
            'lt_tanque_co2' => ['nullable', 'numeric', 'min:0'],
            'kg_tanque_co2' => ['nullable', 'numeric', 'min:0'],
            'pa_sistema_lp' => ['nullable', 'numeric', 'min:0'],
            'presion_sistema_lp' => ['nullable', 'numeric', 'min:0'],
            'nivel_tanque_lp' => ['nullable', 'numeric', 'min:0'],
            'numero_tanque_lp' => ['nullable', 'numeric', 'min:0'],
            'lt_tanque_lp' => ['nullable', 'numeric', 'min:0'],
            'kg_tanque_lp' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string'],
            'infra_turno_id' => ['nullable', 'integer', 'exists:infra_turnos,id'],
            'fecha' => ['nullable', 'date'],
        ];
    }
}
