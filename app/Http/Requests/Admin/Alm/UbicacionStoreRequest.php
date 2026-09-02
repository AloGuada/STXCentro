<?php

namespace App\Http\Requests\Admin\Alm;

use App\Enums\Alm\UbicacionTipo;
use App\Models\Alm\Ubicacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UbicacionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.ubicaciones.crear') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'almacen_id' => ['required', 'integer', 'exists:alm_almacenes,id'],
            'padre_id' => ['nullable', 'integer', 'exists:alm_ubicaciones,id'],
            'codigo' => [
                'required', 'string', 'max:30',
                Rule::unique('alm_ubicaciones', 'codigo')->where('almacen_id', $this->input('almacen_id')),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::in(UbicacionTipo::valores())],
        ];
    }

    /**
     * Un lugar no puede colgar de otro almacén: la dirección se lee subiendo por
     * los padres, y con la cadena partida entre dos bodegas dejaría de nombrar
     * un lugar real.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            if (! $this->filled('padre_id')) {
                return;
            }

            $padre = Ubicacion::find($this->input('padre_id'));

            if ($padre !== null && (int) $padre->almacen_id !== (int) $this->input('almacen_id')) {
                $validator->errors()->add('padre_id', 'Ese lugar es de otro almacén.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.unique' => 'Ese almacén ya tiene un lugar con esa clave.',
            'codigo.required' => 'Escribe la clave que va rotulada en el anaquel.',
            'nombre.required' => 'Ponle un nombre con el que se pueda pedir.',
        ];
    }
}
