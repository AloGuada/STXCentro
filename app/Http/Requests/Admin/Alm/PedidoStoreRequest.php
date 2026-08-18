<?php

namespace App\Http\Requests\Admin\Alm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PedidoStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.pedidos.crear') ?? false;
    }

    /**
     * `obra_id` es opcional a propósito: sin obra es consumo interno de planta,
     * y fabricación, pintura y mantenimiento también piden material.
     *
     * A diferencia de la salida, aquí **sí** se puede pedir más de lo que hay:
     * el almacén decide si surte parcial o si hay que comprar. Pedir de más sólo
     * es un error cuando el material va a salir de verdad.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'almacen_id' => ['required', 'integer', 'exists:alm_almacenes,id'],
            'departamento_id' => ['required', 'integer', 'exists:departamentos,id'],
            'obra_id' => ['nullable', 'integer', 'exists:obras,id'],
            'recibe_nombre' => ['nullable', 'string', 'max:255'],
            'grupo_trabajo_id' => ['nullable', 'integer', 'exists:prod_grupos_trabajo,id'],
            'fecha' => ['required', 'date'],
            'fecha_requerida' => ['required', 'date', 'after_or_equal:fecha'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => [
                'required', 'integer',
                Rule::exists('costos_productos', 'id')->where('controla_inventario', true),
            ],
            'detalles.*.cantidad_solicitada' => ['required', 'numeric', 'gt:0'],
            'detalles.*.observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * `recibe` y la cuadrilla sólo aplican al consumo interno: en un pedido de
     * obra recibe el almacén destino, y anotar ahí a una persona haría creer que
     * alguien firma por material que llega a otro domicilio.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('obra_id')) {
                return;
            }

            foreach (['recibe_nombre', 'grupo_trabajo_id'] as $campo) {
                if ($this->filled($campo)) {
                    $validator->errors()->add(
                        $campo,
                        'En un pedido de obra recibe el almacén destino, no una persona.',
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'almacen_id.required' => 'Indica a qué almacén se le está pidiendo.',
            'departamento_id.required' => 'Indica qué área lo pide: siempre hay un responsable.',
            'fecha_requerida.after_or_equal' => 'No se puede necesitar el material antes de haberlo pedido.',
            'detalles.required' => 'Captura al menos un artículo.',
            'detalles.*.cantidad_solicitada.gt' => 'Pedir cero no es pedir.',
            'detalles.*.producto_id.exists' => 'Ese artículo no lleva kardex: el almacén no lo guarda.',
        ];
    }
}
