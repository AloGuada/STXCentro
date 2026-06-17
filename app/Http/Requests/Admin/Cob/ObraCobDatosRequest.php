<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ObraCobDatosRequest extends FormRequest
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
            'no' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::in(['base', 'adicional'])],
            // Solo lo envía el form de edición; en alta la obra nace 'abierta'.
            'estatus' => ['sometimes', Rule::in(['abierta', 'cerrada'])],
        ];
    }
}
