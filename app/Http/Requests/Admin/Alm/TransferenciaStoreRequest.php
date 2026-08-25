<?php

namespace App\Http\Requests\Admin\Alm;

use App\Models\Alm\Pedido;
use App\Models\Alm\PedidoDetalle;
use App\Services\Alm\AlmacenLedger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * El primer tiempo: sale el camión.
 */
class TransferenciaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.transferencias.enviar') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'almacen_origen_id' => ['required', 'integer', 'exists:alm_almacenes,id'],
            'almacen_destino_id' => ['required', 'integer', 'different:almacen_origen_id', 'exists:alm_almacenes,id'],
            'pedido_id' => ['nullable', 'integer', 'exists:alm_pedidos,id'],
            'fecha_envio' => ['required', 'date', 'before_or_equal:today'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => [
                'required', 'integer',
                Rule::exists('costos_productos', 'id')->where('controla_inventario', true),
            ],
            'detalles.*.pedido_detalle_id' => ['nullable', 'integer', 'exists:alm_pedido_detalle,id'],
            'detalles.*.cantidad_enviada' => ['required', 'numeric', 'gt:0'],
            'detalles.*.observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * El material sale de verdad del origen, así que aquí sí es error mandar más
     * de lo que hay — igual que en una salida.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validarExistencia($validator);
            $this->validarPedido($validator);
        });
    }

    private function validarExistencia(Validator $validator): void
    {
        $ledger = app(AlmacenLedger::class);
        $origenId = $this->integer('almacen_origen_id');
        $porProducto = [];

        foreach ((array) $this->input('detalles', []) as $i => $detalle) {
            $productoId = (int) ($detalle['producto_id'] ?? 0);
            $porProducto[$productoId]['cantidad'] = ($porProducto[$productoId]['cantidad'] ?? 0)
                + (float) ($detalle['cantidad_enviada'] ?? 0);
            $porProducto[$productoId]['renglones'][] = $i;
        }

        $epsilon = (float) config('costos.epsilon_cantidad');

        foreach ($porProducto as $productoId => $datos) {
            $disponible = $ledger->disponible($origenId, $productoId);

            if ($datos['cantidad'] <= $disponible + $epsilon) {
                continue;
            }

            foreach ($datos['renglones'] as $i) {
                $validator->errors()->add(
                    "detalles.{$i}.cantidad_enviada",
                    "No hay existencia suficiente en el origen: hay {$disponible}.",
                );
            }
        }
    }

    /**
     * Sólo un pedido de obra se surte con transferencia: el de planta se queda
     * en el mismo domicilio y sale con una salida. Y el destino tiene que ser un
     * almacén de esa obra, o el material llegaría a otro lado del que se pidió.
     */
    private function validarPedido(Validator $validator): void
    {
        if (! $this->filled('pedido_id')) {
            return;
        }

        $pedido = Pedido::with('almacen')->find($this->integer('pedido_id'));

        if ($pedido === null) {
            return;
        }

        if ((int) $pedido->almacen_id !== $this->integer('almacen_origen_id')) {
            $validator->errors()->add('pedido_id', 'Ese pedido se le hizo a otro almacén.');

            return;
        }

        if (! $pedido->estatus->admiteSurtido()) {
            $validator->errors()->add('pedido_id', 'Ese pedido ya no admite surtido.');

            return;
        }

        if (! $pedido->seSurteConTransferencia()) {
            $validator->errors()->add(
                'pedido_id',
                'Ese pedido es de consumo interno: el material se queda en planta y sale con una salida.',
            );

            return;
        }

        $epsilon = (float) config('costos.epsilon_cantidad');

        foreach ((array) $this->input('detalles', []) as $i => $detalle) {
            if (empty($detalle['pedido_detalle_id'])) {
                continue;
            }

            $renglon = PedidoDetalle::find($detalle['pedido_detalle_id']);

            if ($renglon === null || (int) $renglon->pedido_id !== (int) $pedido->id) {
                $validator->errors()->add("detalles.{$i}.pedido_detalle_id", 'Ese renglón no es de este pedido.');

                continue;
            }

            if ((float) ($detalle['cantidad_enviada'] ?? 0) > $renglon->pendiente() + $epsilon) {
                $validator->errors()->add(
                    "detalles.{$i}.cantidad_enviada",
                    "A ese renglón del pedido sólo le faltan {$renglon->pendiente()}.",
                );
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_envio.before_or_equal' => 'La transferencia no puede salir un día que no ha llegado: el material sale hoy o ya salió.',
            'almacen_destino_id.different' => 'El origen y el destino no pueden ser el mismo almacén.',
            'detalles.required' => 'Captura al menos un artículo.',
            'detalles.*.cantidad_enviada.gt' => 'Enviar cero no es enviar.',
        ];
    }
}
