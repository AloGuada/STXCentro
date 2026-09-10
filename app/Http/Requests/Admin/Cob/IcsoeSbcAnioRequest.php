<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IcsoeSbcAnioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cob.icsoe-sbc.editar') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'anio' => [
                'required',
                'integer',
                'between:2000,2100',
                Rule::unique('cob_icsoe_sbc_anios', 'anio')->ignore($this->route('sbcAnio')),
            ],
            'sbc' => ['required', 'numeric', 'min:0'],
            'costo_m2' => ['required', 'numeric', 'min:0'],
            'prima_riesgo' => ['required', 'numeric', 'between:0,100'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'anio.unique' => 'Ese año ya está capturado en el catálogo.',
            'prima_riesgo.between' => 'La prima de riesgo se captura como porcentaje (0 a 100).',
        ];
    }
}
