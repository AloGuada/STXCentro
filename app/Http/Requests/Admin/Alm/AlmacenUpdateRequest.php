<?php

namespace App\Http\Requests\Admin\Alm;

use App\Enums\Alm\AlmacenTipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AlmacenUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.almacenes.editar') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'clave' => [
                'required', 'string', 'max:20',
                Rule::unique('alm_almacenes', 'clave')
                    ->where('obra_id', $this->input('obra_id'))
                    ->ignore($this->route('almacen')),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'obra_id' => ['nullable', 'integer', 'exists:obras,id'],
            'tipo' => ['required', Rule::in(AlmacenTipo::valores())],
            'responsable_id' => ['nullable', 'uuid', 'exists:usuarios,id'],
            'observaciones' => ['nullable', 'string'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'clave.required' => 'La clave del almacén es obligatoria.',
            'clave.unique' => 'Esa obra ya tiene un almacén con esa clave.',
            'nombre.required' => 'El nombre del almacén es obligatorio.',
        ];
    }
}
