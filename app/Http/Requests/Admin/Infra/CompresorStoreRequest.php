<?php

namespace App\Http\Requests\Admin\Infra;

use Illuminate\Foundation\Http\FormRequest;

class CompresorStoreRequest extends FormRequest
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
            'compresor_1_status' => ['boolean'],
            'compresor_1_presion_aire' => ['nullable', 'numeric', 'min:0'],
            'compresor_1_tiempo_trabajo' => ['nullable', 'numeric', 'min:0'],
            'compresor_1_tiempo_marcha' => ['nullable', 'numeric', 'min:0'],
            'compresor_1_kwhr' => ['nullable', 'numeric', 'min:0'],
            'compresor_2_status' => ['boolean'],
            'compresor_2_presion_aire' => ['nullable', 'numeric', 'min:0'],
            'compresor_2_tiempo_trabajo' => ['nullable', 'numeric', 'min:0'],
            'compresor_2_tiempo_marcha' => ['nullable', 'numeric', 'min:0'],
            'compresor_2_kwhr' => ['nullable', 'numeric', 'min:0'],
            'compresor_3_status' => ['boolean'],
            'compresor_3_presion_aire' => ['nullable', 'numeric', 'min:0'],
            'compresor_3_tiempo_trabajo' => ['nullable', 'numeric', 'min:0'],
            'compresor_3_tiempo_marcha' => ['nullable', 'numeric', 'min:0'],
            'compresor_3_kwhr' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string'],
            'infra_turno_id' => ['nullable', 'integer', 'exists:infra_turnos,id'],
            'fecha' => ['nullable', 'date'],
        ];
    }
}
