<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;

class ResumenCeldaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'fila_id' => ['required', 'exists:cotiz_resumen_filas,id'],
            'columna_id' => ['required', 'exists:cotiz_resumen_columnas,id'],
            'coef' => ['nullable', 'numeric'],
        ];
    }
}
