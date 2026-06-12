<?php

namespace App\Http\Requests\Admin\Cotiz;

use Illuminate\Foundation\Http\FormRequest;

class ObraCuadrillaGlobalUpsertRequest extends FormRequest
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
            'categoria_id' => ['required', 'exists:cotiz_personal_categorias,id'],
            'cantidad_por_grupo' => ['required', 'integer', 'min:0'],
        ];
    }
}
