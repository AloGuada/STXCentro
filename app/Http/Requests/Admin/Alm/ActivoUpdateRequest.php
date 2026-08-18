<?php

namespace App\Http\Requests\Admin\Alm;

use App\Enums\Alm\ActivoEstatus;
use App\Models\Alm\Activo;
use App\Models\Alm\Ubicacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * La corrección de una pieza desde el modal de lápiz del renglón.
 *
 * No se corrige ni el artículo ni el almacén: cambiar de artículo dejaría el
 * saldo del viejo con una pieza fantasma, y mover de bodega es una
 * transferencia, no una edición. La baja tampoco se teclea aquí — descarga
 * existencia, así que tiene su propia acción.
 */
class ActivoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.activos.editar') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'no_serie' => ['required', 'string', 'max:120'],
            'marca' => ['nullable', 'string', 'max:255'],
            'modelo' => ['nullable', 'string', 'max:255'],
            'id_mantenimiento' => ['nullable', 'string', 'max:150'],
            'codigo_barras' => ['nullable', 'string', 'max:255'],
            'ubicacion_id' => ['nullable', 'integer', 'exists:alm_ubicaciones,id'],
            'condicion' => ['nullable', 'string', 'max:255'],
            'estatus' => [
                'required',
                // La baja descarga existencia: va por su propia acción, no por
                // un desplegable de edición.
                Rule::in([
                    ActivoEstatus::Disponible->value,
                    ActivoEstatus::Prestado->value,
                    ActivoEstatus::EnReparacion->value,
                ]),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Activo $activo */
            $activo = $this->route('activo');

            $serie = trim((string) $this->input('no_serie'));

            $repetida = Activo::query()
                ->where('producto_id', $activo->producto_id)
                ->where('no_serie', $serie)
                ->whereKeyNot($activo->id)
                ->exists();

            if ($repetida) {
                $validator->errors()->add('no_serie', 'Otra pieza de este artículo ya tiene esa serie.');
            }

            if (! $this->filled('ubicacion_id')) {
                return;
            }

            $pertenece = Ubicacion::query()
                ->whereKey($this->integer('ubicacion_id'))
                ->where('almacen_id', $activo->almacen_id)
                ->where('activa', true)
                ->exists();

            if (! $pertenece) {
                $validator->errors()->add('ubicacion_id', 'Ese lugar no existe en el almacén de la pieza.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'no_serie.required' => 'La serie es lo que identifica la pieza: no puede quedar vacía.',
            'estatus.in' => 'La baja se registra con su propia acción, porque descarga existencia.',
        ];
    }
}
