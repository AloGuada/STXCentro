<?php

namespace App\Http\Requests\Admin\Alm;

use App\Enums\Alm\UbicacionTipo;
use App\Models\Alm\Ubicacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El almacén de una ubicación no se corrige: mover un rack de bodega dejaría a
 * las existencias que lo mencionan apuntando a otro edificio. Si el lugar
 * estaba mal, se da de baja y se crea el correcto.
 */
class UbicacionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.ubicaciones.editar') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ubicacion = $this->route('ubicacion');

        return [
            'padre_id' => ['nullable', 'integer', 'exists:alm_ubicaciones,id'],
            'codigo' => [
                'required', 'string', 'max:30',
                Rule::unique('alm_ubicaciones', 'codigo')
                    ->where('almacen_id', $ubicacion->almacen_id)
                    ->ignore($ubicacion->id),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::in(UbicacionTipo::valores())],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            $ubicacion = $this->route('ubicacion');

            if (! $this->filled('padre_id')) {
                return;
            }

            $padreId = (int) $this->input('padre_id');

            if ($padreId === (int) $ubicacion->id) {
                $validator->errors()->add('padre_id', 'Un lugar no puede colgar de sí mismo.');

                return;
            }

            $padre = Ubicacion::find($padreId);

            if ($padre === null) {
                return;
            }

            if ((int) $padre->almacen_id !== (int) $ubicacion->almacen_id) {
                $validator->errors()->add('padre_id', 'Ese lugar es de otro almacén.');

                return;
            }

            // Colgar un pasillo de su propio rack deja un ciclo, y el ciclo
            // cuelga a la pantalla al armar el árbol: la recursión no termina.
            $actual = $padre;

            while ($actual !== null) {
                if ((int) $actual->id === (int) $ubicacion->id) {
                    $validator->errors()->add('padre_id', 'Ese lugar ya cuelga de éste.');

                    return;
                }

                $actual = $actual->padre;
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
        ];
    }
}
