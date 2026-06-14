<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;

class ObraEstadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cob.obras.cerrar') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'estatus' => ['required', 'string', 'in:abierta,cerrada'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estatus.required' => 'El estatus es obligatorio.',
            'estatus.in' => 'El estatus debe ser abierta o cerrada.',
        ];
    }
}
