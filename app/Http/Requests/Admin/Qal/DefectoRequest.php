<?php

namespace App\Http\Requests\Admin\Qal;

use App\Enums\Qal\AmbitoDefecto;
use App\Models\Qal\Defecto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y cambio de nombre de un defecto.
 *
 * El nombre es único dentro de su ámbito, no en todo el catálogo: «Otro» existe
 * en soldadura y en pintura, y son defectos distintos. Al editar, el ámbito se
 * ignora aunque venga: se fija al dar de alta.
 */
class DefectoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $defecto = $this->defectoEditado();
        $ambito = $defecto?->ambito->value ?? $this->input('ambito');

        return [
            'ambito' => $defecto ? ['exclude'] : ['required', Rule::enum(AmbitoDefecto::class)],
            'nombre' => [
                'required',
                'string',
                'max:120',
                Rule::unique('qal_defectos', 'nombre')->where('ambito', $ambito)->ignore($defecto?->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ese defecto ya existe en esta lista.',
            'ambito.required' => 'Elige a qué lista pertenece el defecto.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'el defecto',
            'ambito' => 'la lista',
        ];
    }

    private function defectoEditado(): ?Defecto
    {
        $id = $this->route('id');

        return $id === null ? null : Defecto::find($id);
    }
}
