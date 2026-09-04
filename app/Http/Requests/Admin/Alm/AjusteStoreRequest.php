<?php

namespace App\Http\Requests\Admin\Alm;

use App\Enums\Alm\AjusteMotivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AjusteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.ajustes.crear') ?? false;
    }

    /**
     * Se captura **lo contado**, nunca la diferencia: pedir la diferencia obliga
     * al almacenista a hacer la resta y a acertarle al signo.
     *
     * `conteo_fisico` no está entre los motivos capturables: ése lo genera el
     * cierre de una hoja de conteo cíclico, no alguien tecleándolo.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'almacen_id' => ['required', 'integer', 'exists:alm_almacenes,id'],
            'motivo' => [
                'required',
                Rule::in(array_map(fn (AjusteMotivo $m): string => $m->value, AjusteMotivo::capturables())),
            ],
            'fecha' => ['required', 'date'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.articulo_id' => [
                'required', 'integer',
                // Del catálogo de Almacén, como el resto de los documentos. Ya
                // no se pregunta si lleva kardex: tener renglón ahí es llevarlo.
                //
                // Validaba contra `costos_productos` —herencia de cuando el
                // renglón viajaba con `producto_id`—, y los dos catálogos tienen
                // numeración propia: el id de un artículo caía sobre un producto
                // ajeno. Casi siempre pasaba de casualidad y no comprobaba nada;
                // cuando el producto de enfrente no controlaba inventario,
                // rechazaba un artículo que sí existe.
                Rule::exists('alm_articulos', 'id'),
            ],
            'detalles.*.cantidad_contada' => ['required', 'numeric', 'min:0'],
            'detalles.*.costo_unitario' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Un artículo no puede venir dos veces: serían dos conteos del mismo saldo,
     * y el segundo se mediría contra lo que dejó el primero.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $articulos = array_column((array) $this->input('detalles', []), 'articulo_id');

            if (count($articulos) !== count(array_unique($articulos))) {
                $validator->errors()->add('detalles', 'Hay un artículo repetido: cada uno se cuenta una sola vez.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'almacen_id.required' => 'Indica qué almacén se está ajustando.',
            'motivo.required' => 'El motivo es lo que justifica mover el inventario sin un documento.',
            'motivo.in' => 'Ese motivo no se captura a mano: sale del cierre de un conteo.',
            'detalles.required' => 'Captura al menos un renglón contado.',
            'detalles.*.cantidad_contada.required' => 'Escribe cuánto contaste, aunque sea cero.',
            'detalles.*.cantidad_contada.min' => 'Lo contado no puede ser negativo: no se cuenta menos que nada.',
            'detalles.*.articulo_id.exists' => 'Ese artículo no está en el catálogo del almacén.',
        ];
    }
}
