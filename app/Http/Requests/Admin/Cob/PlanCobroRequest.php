<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;

class PlanCobroRequest extends FormRequest
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
            'fecha_inicio_plan' => ['required', 'date'],
            'numero' => ['required', 'integer', 'min:1', 'max:60'],
            'dias' => ['required', 'integer', 'min:1'],
        ];
    }
}
