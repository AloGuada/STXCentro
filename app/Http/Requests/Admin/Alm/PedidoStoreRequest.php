<?php

namespace App\Http\Requests\Admin\Alm;

use App\Models\Usuario;
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
     * `almacen_destino_id` es opcional a propósito: sin destino es consumo
     * interno de planta, y fabricación, pintura y mantenimiento también piden
     * material. Con destino sólo vale un almacén de obra: la obra del pedido se
     * saca de ahí, no se captura aparte.
     *
     * El pedido queda a nombre de un supervisor —quien tiene
     * `alm.pedidos.supervisar`—, no de quien lo teclea: es el nombre que
     * arrastran la salida, el préstamo y la transferencia que lo surten.
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
            'solicitante_id' => [
                'required', 'uuid',
                Rule::exists('usuarios', 'id')->where(fn ($q) => $q->whereIn(
                    'id',
                    Usuario::query()->supervisoresDeAlmacen()->select('id'),
                )),
            ],
            'almacen_destino_id' => [
                'nullable', 'integer', 'different:almacen_id',
                Rule::exists('alm_almacenes', 'id')->whereNotNull('obra_id')->where('activo', true),
            ],
            'recibe_nombre' => ['nullable', 'string', 'max:255'],
            'grupo_trabajo_id' => ['nullable', 'integer', 'exists:prod_grupos_trabajo,id'],
            'fecha' => ['required', 'date'],
            'fecha_requerida' => ['required', 'date', 'after_or_equal:fecha'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.articulo_id' => [
                'required', 'integer',
                // Del catalogo de Almacen. Ya no se pregunta si lleva kardex:
                // tener renglon ahi es llevarlo.
                Rule::exists('alm_articulos', 'id'),
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
            if (! $this->filled('almacen_destino_id')) {
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
            'solicitante_id.required' => 'Indica qué supervisor lo pide: el pedido queda a su nombre.',
            'solicitante_id.exists' => 'Esa persona no es supervisor: sólo quien tiene el permiso puede firmar un pedido.',
            'almacen_destino_id.exists' => 'El destino tiene que ser un almacén de obra; si se queda en planta, déjalo en consumo interno.',
            'almacen_destino_id.different' => 'El destino no puede ser el mismo almacén al que se le pide.',
            'fecha_requerida.after_or_equal' => 'No se puede necesitar el material antes de haberlo pedido.',
            'detalles.required' => 'Captura al menos un artículo.',
            'detalles.*.cantidad_solicitada.gt' => 'Pedir cero no es pedir.',
            'detalles.*.articulo_id.exists' => 'Ese artículo no está en el catálogo del almacén.',
        ];
    }
}
