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
            'detalles.*.producto_id' => [
                'required', 'integer',
                // Sólo lo que lleva kardex: un flete se compra pero no se
                // guarda, así que no hay nada que contar ni saldo que corregir.
                Rule::exists('costos_productos', 'id')->where('controla_inventario', true),
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
            $productos = array_column((array) $this->input('detalles', []), 'producto_id');

            if (count($productos) !== count(array_unique($productos))) {
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
            'detalles.*.producto_id.exists' => 'Ese artículo no lleva kardex, así que no hay existencia que ajustar.',
        ];
    }
}
