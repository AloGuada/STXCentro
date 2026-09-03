<?php

namespace App\Http\Requests\Admin\Alm;

use App\Models\Alm\Activo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ActivoStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.activos.crear') ?? false;
    }

    /**
     * Se dan de alta varias piezas del mismo artículo a la vez: así es como se
     * carga el almacén el primer día, y renglón por renglón nadie lo termina.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'articulo_id' => [
                'required', 'integer',
                // Sólo lo marcado «por pieza» se serializa: darle número de
                // serie a un tornillo no significa nada.
                Rule::exists('alm_articulos', 'id')->where('se_controla_por_pieza', true),
            ],
            'almacen_id' => ['required', 'integer', 'exists:alm_almacenes,id'],
            'ubicacion_id' => ['nullable', 'integer', 'exists:alm_ubicaciones,id'],
            'piezas' => ['required', 'array', 'min:1'],
            'piezas.*.no_serie' => ['required', 'string', 'max:120'],
            'piezas.*.marca' => ['nullable', 'string', 'max:255'],
            'piezas.*.modelo' => ['nullable', 'string', 'max:255'],
            'piezas.*.id_mantenimiento' => ['nullable', 'string', 'max:150'],
            'piezas.*.codigo_barras' => ['nullable', 'string', 'max:255'],
            'piezas.*.costo' => ['nullable', 'numeric', 'min:0'],
            'piezas.*.condicion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * La serie es lo que identifica la pieza, así que se comprueba dos veces:
     * contra lo que ya está dado de alta y contra el resto del mismo formulario
     * —pegar una lista de series repite la misma más seguido de lo que parece.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $productoId = $this->integer('articulo_id');
            $piezas = (array) $this->input('piezas', []);

            $vistas = [];

            foreach ($piezas as $i => $pieza) {
                $serie = trim((string) ($pieza['no_serie'] ?? ''));

                if ($serie === '') {
                    continue;
                }

                $clave = mb_strtolower($serie);

                if (isset($vistas[$clave])) {
                    $validator->errors()->add(
                        "piezas.{$i}.no_serie",
                        "La serie «{$serie}» viene dos veces en esta alta.",
                    );

                    continue;
                }

                $vistas[$clave] = true;

                $existe = Activo::query()
                    ->where('articulo_id', $productoId)
                    ->where('no_serie', $serie)
                    ->exists();

                if ($existe) {
                    $validator->errors()->add(
                        "piezas.{$i}.no_serie",
                        "Ya hay una pieza de este artículo con la serie «{$serie}».",
                    );
                }
            }

            $this->validarUbicacion($validator);
        });
    }

    /**
     * El lugar tiene que ser del almacén al que se están dando de alta: si no,
     * la pieza quedaría «en» un rack de otra bodega.
     */
    private function validarUbicacion(Validator $validator): void
    {
        if (! $this->filled('ubicacion_id')) {
            return;
        }

        $pertenece = \App\Models\Alm\Ubicacion::query()
            ->whereKey($this->integer('ubicacion_id'))
            ->where('almacen_id', $this->integer('almacen_id'))
            ->where('activa', true)
            ->exists();

        if (! $pertenece) {
            $validator->errors()->add('ubicacion_id', 'Ese lugar no existe en este almacén o ya está dado de baja.');
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'articulo_id.required' => 'Indica de qué artículo son las piezas.',
            'articulo_id.exists' => 'Ese artículo no se controla por pieza: márcalo primero en el catálogo.',
            'almacen_id.required' => 'Indica en qué almacén quedan.',
            'piezas.required' => 'Captura al menos una pieza con su número de serie.',
            'piezas.*.no_serie.required' => 'Cada pieza necesita su número de serie: es lo que la identifica.',
        ];
    }
}
