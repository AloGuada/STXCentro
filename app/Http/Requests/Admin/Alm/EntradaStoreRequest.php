<?php

namespace App\Http\Requests\Admin\Alm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La entrada **sin orden de compra**: material que llega sin compra de por
 * medio.
 *
 * La recepción contra una orden no pasa por aquí — la valida
 * `Costos\EntregaStoreRequest`, que es donde vive el tope contra lo pedido y lo
 * facturado.
 */
class EntradaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.entradas.crear') ?? false;
    }

    /**
     * `precio_unitario` es obligatorio: sin orden no hay de dónde heredarlo, y
     * material que entra sin costo deja el promedio del artículo mintiendo
     * sobre lo que vale el inventario.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'almacen_id' => ['required', 'integer', 'exists:alm_almacenes,id'],
            'fecha_entrega' => ['required', 'date'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => [
                'required', 'integer',
                Rule::exists('costos_productos', 'id')->where('controla_inventario', true),
            ],
            'detalles.*.cantidad_recibida' => ['required', 'numeric', 'gt:0'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
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
            'detalles.required' => 'Captura al menos un artículo.',
            'detalles.*.cantidad_recibida.gt' => 'Recibir cero no es recibir.',
            'detalles.*.precio_unitario.required' => 'Sin orden de compra no hay de dónde sacar el costo: captúralo.',
            'detalles.*.producto_id.exists' => 'Ese artículo no lleva kardex: el almacén no lo guarda.',
        ];
    }
}
