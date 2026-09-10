<?php

namespace App\Http\Requests\Admin\Alm;

use App\Enums\Alm\AlmacenTipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

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
                $this->claveUnica()->ignore($this->route('almacen')),
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
     * La clave es única dentro de la obra. Los almacenes centrales (los de la
     * planta) no tienen obra, y `where('obra_id', null)` compara contra NULL, que
     * en SQL nunca empata: hay que preguntar explícitamente por el nulo o la
     * validación deja pasar claves repetidas entre ellos.
     */
    private function claveUnica(): Unique
    {
        $obraId = $this->input('obra_id');

        $regla = Rule::unique('alm_almacenes', 'clave');

        return $obraId === null || $obraId === ''
            ? $regla->whereNull('obra_id')
            : $regla->where('obra_id', $obraId);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'clave.required' => 'La clave del almacén es obligatoria.',
            'clave.unique' => $this->filled('obra_id')
                ? 'Esa obra ya tiene un almacén con esa clave.'
                : 'Ya hay un almacén central con esa clave.',
            'nombre.required' => 'El nombre del almacén es obligatorio.',
        ];
    }
}
