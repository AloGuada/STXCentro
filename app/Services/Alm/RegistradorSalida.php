<?php

namespace App\Services\Alm;

use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Movimiento;
use App\Models\Alm\Pedido;
use App\Models\Alm\Salida;
use Illuminate\Database\Eloquent\Collection;
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
    public function registrar(array $cabecera, array $renglones, ?string $userId = null, bool $permitirAjena = false): Salida
    {
        return DB::transaction(function () use ($cabecera, $renglones, $userId, $permitirAjena): Salida {
            $salida = Salida::create($cabecera);

            foreach ($renglones as $renglon) {
                $articuloId = (int) $renglon['articulo_id'];
                $cantidad = (float) $renglon['cantidad'];

                // La descarga sale al costo promedio vigente; el ledger lo
                // devuelve ya resuelto y aquí se sella, para no tener que
                // reconstruirlo desde el kardex al reimprimir el vale.
                $movimiento = $this->ledger->registrarPorArticulo(
                    almacenId: $salida->almacen_id,
                    articuloId: $articuloId,
                    tipo: MovimientoTipo::Salida,
                    cantidad: -$cantidad,
                    documento: $salida,
                    referencia: $salida->folio,
                    observaciones: $renglon['observaciones'] ?? null,
                    userId: $userId,
                    obraId: $salida->obra_destino_id === null ? null : (int) $salida->obra_destino_id,
                    permitirAjena: $permitirAjena,
                );

                $salida->detalles()->create([
                    'articulo_id' => $articuloId,
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

            // Asiento por asiento y no renglón por renglón: una salida que tomó
            // 20 de su obra y 10 de lo libre dejó dos asientos, y cada parte
            // tiene que volver a donde estaba. Devolverle los 30 a la obra la
            // dejaría con material que nunca fue suyo.
            foreach ($this->asientosDe($salida) as $movimiento) {
                $this->ledger->registrarPorArticulo(
                    almacenId: $salida->almacen_id,
                    articuloId: (int) $movimiento->articulo_id,
                    tipo: MovimientoTipo::Salida,
                    cantidad: -(float) $movimiento->cantidad,
                    costoUnitario: $movimiento->costo_unitario === null ? null : (float) $movimiento->costo_unitario,
                    documento: $salida,
                    referencia: $salida->folio,
                    observaciones: "Cancelación · {$motivo}",
                    userId: $userId,
                    esReverso: true,
                    obraId: $movimiento->obra_id === null ? null : (int) $movimiento->obra_id,
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

    /**
     * Los asientos que dejó esta salida, sin contar reversos previos.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Movimiento>
     */
    private function asientosDe(Salida $salida): Collection
    {
        return Movimiento::query()
            ->where('documento_type', $salida->getMorphClass())
            ->where('documento_id', $salida->getKey())
            ->where('es_reverso', false)
            ->cronologico()
            ->get();
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
}
