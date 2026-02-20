<?php

namespace App\Http\Requests\Admin\Infra;

use Illuminate\Foundation\Http\FormRequest;

class PtarStoreRequest extends FormRequest
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
            'soplador_activa' => ['boolean'],
            'bomba_activa' => ['boolean'],
            'nivel_cloro' => ['nullable', 'numeric', 'min:0'],
            'trampa_solida' => ['boolean'],
            'observaciones' => ['nullable', 'string'],
            'infra_turno_id' => ['nullable', 'integer', 'exists:infra_turnos,id'],
            'fecha' => ['nullable', 'date'],
        ];
    }
}
