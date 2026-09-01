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
 *
 * `fecha_entrega` es la fecha de la transacción —cuándo entró el material— y la
 * elige quien captura: el camión llegó el viernes y el almacén lo asienta el
 * lunes. No puede ser futura. Cuándo se capturó lo guarda el servidor aparte,
 * en `created_at`, y es lo que responde "¿esto se fechó hacia atrás?".
 *
 * Contra orden, la recepción siempre queda amparada por una factura, y se llega
 * a ella por uno de dos caminos: eligiendo la que el proveedor ya subió al
 * portal (`factura_id`), o adjuntando la que llegó con el material (`xml` y
 * `pdf`). El segundo es el normal, y por eso los archivos son obligatorios en
 * cuanto no se eligió una factura existente.
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

        // La factura llega con el material salvo que el proveedor se haya
        // adelantado por el portal, que es cuando viene `factura_id`.
        $conFacturaNueva = $conOrden && ! $this->filled('factura_id');

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
            'xml' => [Rule::requiredIf($conFacturaNueva), 'nullable', 'file', 'mimes:xml,txt', 'max:5120'],
            'pdf' => [Rule::requiredIf($conFacturaNueva), 'nullable', 'file', 'mimes:pdf', 'max:10240'],

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
            'xml.required' => 'Adjunta el XML del CFDI, o elige la factura si el proveedor ya la subió.',
            'xml.mimes' => 'El archivo debe ser un XML válido.',
            'pdf.required' => 'Adjunta el PDF de la factura.',
            'pdf.mimes' => 'La factura debe venir en PDF.',
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
