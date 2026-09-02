<?php

namespace App\Services\Alm;

use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Pedido;
use App\Models\Alm\Salida;
use App\Models\Costos\Producto;
use Illuminate\Support\Facades\DB;

/**
 * Graba una salida, la descuenta del kardex y recalcula el pedido que surtió.
 *
 * Los tres pasos van en una transacción: si el kardex falla por existencia
 * insuficiente, el documento no debe quedar grabado prometiendo material que
 * nunca salió.
 */
class RegistradorSalida
{
    public function __construct(
        private readonly AlmacenLedger $ledger,
        private readonly SurtidoPedido $surtido,
    ) {}

    /**
     * @param  array<string, mixed>  $cabecera
     * @param  list<array<string, mixed>>  $renglones
     */
    public function registrar(array $cabecera, array $renglones, ?string $userId = null): Salida
    {
        return DB::transaction(function () use ($cabecera, $renglones, $userId): Salida {
            $salida = Salida::create($cabecera);

            foreach ($renglones as $renglon) {
                $productoId = (int) $renglon['producto_id'];
                $cantidad = (float) $renglon['cantidad'];

                // La descarga sale al costo promedio vigente; el ledger lo
                // devuelve ya resuelto y aquí se sella, para no tener que
                // reconstruirlo desde el kardex al reimprimir el vale.
                $movimiento = $this->llevaKardex($productoId)
                    ? $this->ledger->registrarPorProducto(
                        almacenId: $salida->almacen_id,
                        productoId: $productoId,
                        tipo: MovimientoTipo::Salida,
                        cantidad: -$cantidad,
                        documento: $salida,
                        referencia: $salida->folio,
                        observaciones: $renglon['observaciones'] ?? null,
                        userId: $userId,
                    )
                    : null;

                $salida->detalles()->create([
                    'producto_id' => $productoId,
                    'pedido_detalle_id' => $renglon['pedido_detalle_id'] ?? null,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $movimiento?->costo_unitario,
                    'observaciones' => $renglon['observaciones'] ?? null,
                ]);
            }

            $this->recalcularPedido($salida);

            return $salida;
        });
    }

    /**
     * Cancela la salida y devuelve el material al kardex.
     *
     * El reverso es un movimiento espejo, no un borrado: el folio y los dos
     * asientos quedan, que es lo que permite explicar después por qué el saldo
     * subió y bajó el mismo día.
     */
    public function cancelar(Salida $salida, string $motivo, ?string $userId = null): Salida
    {
        return DB::transaction(function () use ($salida, $motivo, $userId): Salida {
            if ($salida->estaCancelada()) {
                return $salida;
            }

            foreach ($salida->detalles as $detalle) {
                $this->ledger->registrarPorProducto(
                    almacenId: $salida->almacen_id,
                    productoId: (int) $detalle->producto_id,
                    tipo: MovimientoTipo::Salida,
                    cantidad: (float) $detalle->cantidad,
                    costoUnitario: $detalle->costo_unitario === null ? null : (float) $detalle->costo_unitario,
                    documento: $salida,
                    referencia: $salida->folio,
                    observaciones: "Cancelación · {$motivo}",
                    userId: $userId,
                    esReverso: true,
                );
            }

            $salida->update([
                'cancelada_at' => now(),
                'cancelada_por' => $userId,
                'motivo_cancelacion' => $motivo,
            ]);

            // El pedido vuelve a deber lo que esta salida decía haber entregado.
            $this->recalcularPedido($salida);

            return $salida;
        });
    }

    private function recalcularPedido(Salida $salida): void
    {
        if ($salida->pedido_id === null) {
            return;
        }

        $pedido = Pedido::with('detalles')->find($salida->pedido_id);

        if ($pedido !== null) {
            $this->surtido->recalcular($pedido);
        }
    }

    private function llevaKardex(int $productoId): bool
    {
        return Producto::query()
            ->whereKey($productoId)
            ->where('controla_inventario', true)
            ->exists();
    }
}
