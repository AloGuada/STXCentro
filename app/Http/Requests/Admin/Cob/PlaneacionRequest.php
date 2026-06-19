<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;

class PlaneacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan' => ['array'],
            'plan.*.orden' => ['required', 'integer', 'min:1'],
            'plan.*.fecha_inicio_plan' => ['required', 'date'],
            'plan.*.fecha_fin_plan' => ['required', 'date'],

            'etapas' => ['array'],
            'etapas.*.id' => ['required', 'integer'],
            'etapas.*.fecha_inicio_plan' => ['required', 'date'],
            'etapas.*.fecha_fin_plan' => ['required', 'date'],
        ];
    }
}
