<?php

namespace App\Services\Alm;

use App\Enums\Alm\MovimientoTipo;
use App\Enums\Alm\TransferenciaEstatus;
use App\Models\Alm\Almacen;
use App\Models\Alm\Pedido;
use App\Models\Alm\Transferencia;
use App\Models\Costos\Producto;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Los dos tiempos de una transferencia.
 *
 * El envío graba **un** movimiento (el origen descarga) y la recepción el
 * segundo (el destino carga, por lo confirmado). Entre los dos, la suma de las
 * existencias **no cuadra**: ése es el punto, y lo que falta se lee de las
 * transferencias abiertas, no de una columna.
 */
class RegistradorTransferencia
{
    public function __construct(
        private readonly AlmacenLedger $ledger,
        private readonly SurtidoPedido $surtido,
    ) {}

    /**
     * Primer tiempo: sale el camión.
     *
     * El costo con el que descarga el origen se sella en el renglón, para que el
     * destino cargue con el mismo número. Si cada extremo usara su propio
     * promedio, mover material entre bodegas inventaría o destruiría valor.
     *
     * @param  array<string, mixed>  $cabecera
     * @param  list<array<string, mixed>>  $renglones
     */
    public function enviar(array $cabecera, array $renglones, ?string $userId = null): Transferencia
    {
        return DB::transaction(function () use ($cabecera, $renglones, $userId): Transferencia {
            $transferencia = Transferencia::create([
                ...$cabecera,
                'estatus' => TransferenciaEstatus::EnTransito,
            ]);

            foreach ($renglones as $renglon) {
                $productoId = (int) $renglon['producto_id'];
                $cantidad = (float) $renglon['cantidad_enviada'];

                $movimiento = $this->llevaKardex($productoId)
                    ? $this->ledger->registrarPorProducto(
                        almacenId: $transferencia->almacen_origen_id,
                        productoId: $productoId,
                        tipo: MovimientoTipo::TransferenciaSalida,
                        cantidad: -$cantidad,
                        documento: $transferencia,
                        referencia: $transferencia->folio,
                        observaciones: $renglon['observaciones'] ?? null,
                        userId: $userId,
                    )
                    : null;

                $transferencia->detalles()->create([
                    'producto_id' => $productoId,
                    'pedido_detalle_id' => $renglon['pedido_detalle_id'] ?? null,
                    'cantidad_enviada' => $cantidad,
                    'cantidad_recibida' => null,
                    'costo_unitario' => $movimiento?->costo_unitario,
                    'observaciones' => $renglon['observaciones'] ?? null,
                ]);
            }

            // El pedido se descuenta con **lo enviado**, no con lo confirmado: el
            // origen ya cumplió y no tiene el material para volver a surtirlo.
            $this->recalcularPedido($transferencia);

            return $transferencia;
        });
    }

    /**
     * Segundo tiempo: el destino confirma qué bajó del camión.
     *
     * Carga el destino **por lo confirmado**. La diferencia no genera un tercer
     * movimiento: ya desapareció en el `transferencia_salida` del origen. Lo
     * único que agrega la recepción es el dueño del faltante.
     *
     * @param  array<int, float>  $confirmado  id de renglón => cantidad recibida
     */
    public function recibir(
        Transferencia $transferencia,
        array $confirmado,
        ?string $faltanteResponsableId = null,
        ?string $userId = null,
    ): Transferencia {
        return DB::transaction(function () use ($transferencia, $confirmado, $faltanteResponsableId, $userId): Transferencia {
            if (! $transferencia->vaEnCamino()) {
                throw new RuntimeException('Esta transferencia ya se recibió o está cancelada.');
            }

            foreach ($transferencia->detalles as $detalle) {
                $recibida = (float) ($confirmado[$detalle->id] ?? 0);

                $detalle->update(['cantidad_recibida' => $recibida]);

                if ($recibida <= 0 || ! $this->llevaKardex((int) $detalle->producto_id)) {
                    continue;
                }

                $this->ledger->registrarPorProducto(
                    almacenId: $transferencia->almacen_destino_id,
                    productoId: (int) $detalle->producto_id,
                    tipo: MovimientoTipo::TransferenciaEntrada,
                    cantidad: $recibida,
                    // El mismo costo con el que salió del origen.
                    costoUnitario: $detalle->costo_unitario === null ? null : (float) $detalle->costo_unitario,
                    documento: $transferencia,
                    referencia: $transferencia->folio,
                    userId: $userId,
                );
            }

            $transferencia->update([
                'estatus' => TransferenciaEstatus::Recibida,
                'fecha_recepcion' => now()->toDateString(),
                'recibido_por' => $userId,
                'faltante_responsable_id' => $faltanteResponsableId,
            ]);

            return $transferencia->refresh();
        });
    }

    /**
     * Cancelar sólo se puede mientras el material va en el camión: una vez que
     * el destino confirmó, el material ya está allá y devolverlo es otra
     * transferencia, no un deshacer.
     */
    public function cancelar(Transferencia $transferencia, string $motivo, ?string $userId = null): Transferencia
    {
        return DB::transaction(function () use ($transferencia, $motivo, $userId): Transferencia {
            if ($transferencia->estaCancelada()) {
                return $transferencia;
            }

            if (! $transferencia->estatus->vaEnCamino()) {
                throw new RuntimeException(
                    'Esta transferencia ya se recibió: devolver el material es otra transferencia.',
                );
            }

            foreach ($transferencia->detalles as $detalle) {
                $this->ledger->registrarPorProducto(
                    almacenId: $transferencia->almacen_origen_id,
                    productoId: (int) $detalle->producto_id,
                    tipo: MovimientoTipo::TransferenciaSalida,
                    cantidad: (float) $detalle->cantidad_enviada,
                    costoUnitario: $detalle->costo_unitario === null ? null : (float) $detalle->costo_unitario,
                    documento: $transferencia,
                    referencia: $transferencia->folio,
                    observaciones: "Cancelación · {$motivo}",
                    userId: $userId,
                    esReverso: true,
                );
            }

            $transferencia->update([
                'cancelada_at' => now(),
                'cancelada_por' => $userId,
                'motivo_cancelacion' => $motivo,
            ]);

            $this->recalcularPedido($transferencia);

            return $transferencia;
        });
    }

    /** Los destinos válidos: cualquier otro almacén activo. */
    public function destinosDe(Almacen $origen): \Illuminate\Support\Collection
    {
        return Almacen::query()
            ->activos()
            ->whereKeyNot($origen->id)
            ->with('obra:id,no')
            ->orderBy('obra_id')
            ->orderBy('clave')
            ->get(['id', 'clave', 'nombre', 'obra_id', 'tipo']);
    }

    private function recalcularPedido(Transferencia $transferencia): void
    {
        if ($transferencia->pedido_id === null) {
            return;
        }

        $pedido = Pedido::with('detalles')->find($transferencia->pedido_id);

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
