<?php

namespace App\Http\Requests\Admin\Infra;

use Illuminate\Foundation\Http\FormRequest;

class BombaStoreRequest extends FormRequest
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
            'bomba_posos_1' => ['boolean'],
            'bomba_posos_2' => ['boolean'],
            'bomba_planta_1' => ['boolean'],
            'bomba_planta_2' => ['boolean'],
            'bomba_planta_3' => ['boolean'],
            'nivel_salmuera' => ['nullable', 'numeric', 'min:0'],
            'nivel_tinaco' => ['nullable', 'numeric', 'min:0'],
            'nivel_sisterna' => ['nullable', 'numeric', 'min:0'],
            'presion_tuberia' => ['nullable', 'numeric', 'min:0'],
            'nivel_hipoclorito' => ['nullable', 'numeric', 'min:0'],
            'nivel_anticongelante' => ['nullable', 'numeric', 'min:0'],
            'aceite_del_motor' => ['nullable', 'numeric', 'min:0'],
            'tanque_diesel' => ['nullable', 'numeric', 'min:0'],
            'voltaje_bateria' => ['nullable', 'numeric', 'min:0'],
            'bomba_jockey' => ['boolean'],
            'bomba_electrica' => ['boolean'],
            'bomba_diesel' => ['boolean'],
            'presion_tuberia_incendio' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string'],
            'infra_turno_id' => ['nullable', 'integer', 'exists:infra_turnos,id'],
            'fecha' => ['nullable', 'date'],
        ];
    }
}
