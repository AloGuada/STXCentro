<?php

namespace App\Http\Requests\Admin\Alm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La entrada al almacén, en sus dos formas: contra una orden de compra —lo
 * normal, material de proveedor— o sin orden, cuando el material llega sin
 * compra de por medio.
 *
 * El almacén es obligatorio en las dos: una entrada que no dice a dónde entra
 * no sube el valor de ningún inventario, que es justo lo que esta pantalla
 * registra.
 */
class EntradaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.entradas.crear') ?? false;
    }

    public function esConOrden(): bool
    {
        return $this->filled('orden_compra_id');
    }

    /**
     * Con orden, la cantidad y el precio se validan contra la partida (el
     * precio es opcional: si no se captura, se hereda el de la orden). Sin
     * orden, el precio es obligatorio: no hay de dónde heredarlo, y material
     * que entra sin costo deja el promedio del artículo mintiendo sobre lo que
     * vale el inventario.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $conOrden = $this->esConOrden();

        return [
            'almacen_id' => ['required', 'integer', 'exists:alm_almacenes,id'],
            'orden_compra_id' => ['nullable', 'integer', 'exists:costos_ordenes_compra,id'],
            'fecha_entrega' => ['required', 'date', 'before_or_equal:today'],
            'observaciones' => ['nullable', 'string', 'max:1000'],

            // Solo la recepción contra orden distingue parcial de completa y
            // puede colgar de una factura.
            'tipo' => [Rule::requiredIf($conOrden), 'in:parcial,completa'],
            'factura_id' => ['nullable', 'integer', 'exists:costos_facturas,id'],
            'completa_factura' => ['nullable', 'boolean'],
            'archivo' => ['nullable', 'file', 'max:10240'],

            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.orden_compra_detalle_id' => [
                Rule::requiredIf($conOrden),
                'exclude_if:orden_compra_id,null',
                'integer',
                'exists:costos_ordenes_compra_detalle,id',
            ],
            'detalles.*.producto_id' => [
                Rule::requiredIf(! $conOrden),
                'nullable',
                'integer',
                Rule::exists('costos_productos', 'id')->where('controla_inventario', true),
            ],
            'detalles.*.cantidad_recibida' => ['required', 'numeric', 'gt:0'],
            'detalles.*.precio_unitario' => [Rule::requiredIf(! $conOrden), 'nullable', 'numeric', 'min:0'],
            'detalles.*.observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'almacen_id.required' => 'Indica a qué almacén entra el material.',
            'fecha_entrega.before_or_equal' => 'La entrada no puede ser de un día que no ha llegado: el material entró hoy o ya había entrado.',
            'tipo.required' => 'Indica si la orden se recibe completa o parcial.',
            'detalles.required' => 'Captura al menos un artículo.',
            'detalles.min' => 'Captura al menos un artículo.',
            'detalles.*.cantidad_recibida.gt' => 'Recibir cero no es recibir.',
            'detalles.*.orden_compra_detalle_id.required' => 'Indica de qué partida de la orden es este renglón.',
            'detalles.*.precio_unitario.required' => 'Sin orden de compra no hay de dónde sacar el costo: captúralo.',
            'detalles.*.producto_id.required' => 'Elige el artículo que entra.',
            'detalles.*.producto_id.exists' => 'Ese artículo no lleva kardex: el almacén no lo guarda.',
        ];
    }
}
