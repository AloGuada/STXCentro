<?php

namespace App\Http\Requests\Admin\Alm;

use App\Enums\Alm\ProductoTipo;
use App\Models\Alm\Activo;
use App\Models\Alm\Articulo;
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
     * Qué se pide depende de cómo está configurado el artículo en el catálogo.
     *
     * Un activo **por pieza** se da de alta serie por serie: varias de un golpe,
     * porque así se carga el almacén el primer día. Un activo **por cantidad**
     * —extensiones, arneses, lo que no lleva serie— es un solo renglón: se dice
     * cuántos entran y a qué costo, y el kardex recibe un solo asiento.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglas = [
            'articulo_id' => [
                'required', 'integer',
                // Sólo los activos: un insumo se gasta, no se da de alta aquí.
                Rule::exists('alm_articulos', 'id')->where('tipo', ProductoTipo::Activo->value),
            ],
            'almacen_id' => ['required', 'integer', 'exists:alm_almacenes,id'],
            'ubicacion_id' => ['nullable', 'integer', 'exists:alm_ubicaciones,id'],
        ];

        if ($this->esPorCantidad()) {
            return $reglas + [
                'cantidad' => ['required', 'numeric', 'gt:0'],
                'costo' => ['nullable', 'numeric', 'min:0'],
                'observaciones' => ['nullable', 'string', 'max:500'],
            ];
        }

        return $reglas + [
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
     * El artículo que se está dando de alta, para saber en qué modo va. Nulo
     * mientras el id no exista: de eso ya se encarga la regla `exists`.
     */
    public function articulo(): ?Articulo
    {
        return Articulo::query()->find($this->integer('articulo_id'));
    }

    /** Activo sin serie: un solo renglón por cantidad. */
    public function esPorCantidad(): bool
    {
        $articulo = $this->articulo();

        return $articulo !== null && $articulo->esActivoPorCantidad();
    }

    /**
     * La serie es lo que identifica la pieza, así que se comprueba dos veces:
     * contra lo que ya está dado de alta y contra el resto del mismo formulario
     * —pegar una lista de series repite la misma más seguido de lo que parece.
     * Por cantidad no hay series que comprobar.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->esPorCantidad()) {
                $this->validarSeries($validator);
            }

            $this->validarUbicacion($validator);
        });
    }

    private function validarSeries(Validator $validator): void
    {
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
            'articulo_id.required' => 'Indica de qué artículo es el alta.',
            'articulo_id.exists' => 'Ese artículo no es un activo: márcalo primero en el catálogo.',
            'almacen_id.required' => 'Indica en qué almacén queda.',
            'cantidad.required' => 'Di cuántos entran: este activo se lleva por cantidad, no por serie.',
            'cantidad.gt' => 'La cantidad tiene que ser mayor que cero.',
            'piezas.required' => 'Captura al menos una pieza con su número de serie.',
            'piezas.*.no_serie.required' => 'Cada pieza necesita su número de serie: es lo que la identifica.',
        ];
    }
}
