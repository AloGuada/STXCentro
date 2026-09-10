<?php

namespace App\Http\Requests\Admin\Alm;

use Illuminate\Foundation\Http\FormRequest;

class ConteoCapturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.conteos.capturar') ?? false;
    }

    /**
     * Se captura **lo contado**, nunca la diferencia. La cantidad puede venir
     * vacía: es borrar una captura que se tecleó por error, no contar cero.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'renglones' => ['required', 'array', 'min:1'],
            'renglones.*.id' => ['required', 'integer', 'distinct'],
            'renglones.*.cantidad_contada' => ['nullable', 'numeric', 'min:0'],
            'renglones.*.observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'renglones.required' => 'No llegó ningún renglón que guardar.',
            'renglones.*.cantidad_contada.numeric' => 'Lo contado tiene que ser un número.',
            'renglones.*.cantidad_contada.min' => 'No se puede contar en negativo: si no hay, es cero.',
        ];
    }
}
