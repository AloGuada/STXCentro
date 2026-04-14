<?php

namespace App\Http\Requests\Admin\Dg;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CarpetaRequest extends FormRequest
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
        $id = $this->route('carpeta')?->id;

        return [
            'nombre' => ['required', 'string', 'max:255', Rule::unique('dg_carpetas', 'nombre')->ignore($id)],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }
}
