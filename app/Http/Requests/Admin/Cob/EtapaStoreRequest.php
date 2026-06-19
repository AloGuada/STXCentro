<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;

class EtapaStoreRequest extends FormRequest
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
            'obra_id' => ['required', 'integer', 'exists:obras,id'],
            'descripcion' => ['required', 'string', 'max:255'],
            'fecha_inicio_plan' => ['required', 'date'],
            'fecha_fin_plan' => ['required', 'date'],
        ];
    }
}
