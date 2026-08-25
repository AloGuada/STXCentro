<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IcsoeMesesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cob.icsoe.editar') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'meses' => ['required', 'array'],
            'meses.*.id' => [
                'required',
                'integer',
                // Acotado al seguimiento de la ruta: sin esto se podrían editar
                // los meses de otro proyecto.
                Rule::exists('cob_icsoe_meses', 'id')
                    ->where('seguimiento_id', $this->route('seguimiento')?->id),
            ],
            'meses.*.dias_cotizados' => ['required', 'numeric', 'min:0'],
            'meses.*.sbc_aplicado' => ['required', 'numeric', 'min:0'],
        ];
    }
}
