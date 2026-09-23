<?php

namespace App\Services\Costos;

use App\Enums\Costos\CancelacionUnidadesEstatus;
use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\NotaCreditoEstatus;
use App\Enums\Costos\OrdenCompraEstatus;
use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Costos\ConfiguracionCostos;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\Factura;
use App\Models\Costos\NotaCredito;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\OrdenCompraDetalleCancelacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancelar unidades de una orden de compra ya emitida.
 *
 * El caso que lo pidió: una partida de 100 piezas con 60 recibidas donde el
 * proveedor ya no va a surtir las 40 que faltan. La cantidad pedida no se
 * toca; lo cancelado se anota aparte y se descuenta del saldo por recibir, del
 * denominador de los porcentajes y del total de la orden.
 *
 * Qué se puede cancelar lo dicen dos topes, y manda el menor:
 *
 * - **Lo que no ha llegado.** Cada entrada de almacén viene con su factura, así
 *   que lo recibido ya está amparado y no se cancela: sólo lo que sigue sin
 *   recibir.
 * - **Lo que no está facturado.** Una factura puede adelantarse al material
 *   (el proveedor la sube al portal antes de surtir). Esas unidades no se han
 *   recibido, pero ya se cobraron: cancelarlas dejaría a la orden facturada
 *   por más de lo que pidió. El tope es el importe de la orden que ninguna
 *   factura viva ampara, traducido a unidades de la partida.
 *
 * Nada surte efecto sin la autorización del jefe de compras: `solicitar()`
 * sólo deja el registro pendiente —y eso ya reporta la orden como pendiente de
 * aprobación—, y es `autorizar()` quien escribe la cantidad, baja el total y
 * revierte el presupuesto.
 */
class CanceladorDeUnidades
{
    public function __construct(
        private readonly ApartadoPresupuestal $presupuesto,
        private readonly OrdenCompraEstadoService $estado,
    ) {}

    /**
     * Lo que todavía se puede cancelar de una partida: el menor de los dos
     * topes de {@see desglose()}.
     */
    public function cancelable(OrdenCompraDetalle $partida, ?int $excluyendo = null): float
    {
        return $this->desglose($partida, $excluyendo)['cancelable'];
    }

    /**
     * Cuánto se puede cancelar de una partida y por qué no más.
     *
     * - `recibido`: unidades que ya entraron en recepciones vigentes.
     * - `recibido_facturado`: de ésas, las que entraron con una factura viva.
     * - `sin_recibir`: lo pedido menos lo cancelado, lo recibido y lo que otra
     *   cancelación pendiente ya tiene tomado.
     * - `tope_facturas`: unidades que caben en el importe de la orden que
     *   ninguna factura viva ampara. Nulo cuando la orden no tiene facturas.
     * - `cancelable`: el menor de `sin_recibir` y `tope_facturas`.
     *
     * Lo recibido se cuenta **bruto**, sin descontar devoluciones, igual que el
     * tope de {@see RegistradorRecepcion::validarSaldos()}: si aquí se contara
     * el neto, una devolución abriría espacio para cancelar unidades que sí
     * llegaron.
     *
     * @return array{recibido: float, recibido_facturado: float, sin_recibir: float, tope_facturas: ?float, cancelable: float, importe_sin_facturar: ?float}
     */
    public function desglose(OrdenCompraDetalle $partida, ?int $excluyendo = null): array
    {
        return $this->desgloseConSaldo(
            $partida,
            $this->importeSinFacturar($partida->ordenCompra, $excluyendo),
            $excluyendo,
        );
    }

    /**
     * El desglose de cada partida de la orden, indexado por id de partida. El
     * importe sin facturar es de la orden entera y se mide una sola vez.
     *
     * @return array<int, array{recibido: float, recibido_facturado: float, sin_recibir: float, tope_facturas: ?float, cancelable: float, importe_sin_facturar: ?float}>
     */
    public function desglosePorOrden(OrdenCompra $orden): array
    {
        $importeSinFacturar = $this->importeSinFacturar($orden);

        return $orden->detalles
            ->mapWithKeys(fn (OrdenCompraDetalle $partida): array => [
                (int) $partida->getKey() => $this->desgloseConSaldo($partida, $importeSinFacturar),
            ])
            ->all();
    }

    /**
     * Deja la cancelación pendiente de que la autorice el jefe de compras.
     *
     * @throws ValidationException
     */
    public function solicitar(OrdenCompraDetalle $partida, float $cantidad, string $motivo, ?string $userId = null): OrdenCompraDetalleCancelacion
    {
        $orden = $partida->ordenCompra;

        if ($orden->estatus === OrdenCompraEstatus::Cancelada) {
            throw ValidationException::withMessages(['cantidad' => 'La orden está cancelada completa.']);
        }

        if ($cantidad <= 0) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad a cancelar debe ser mayor que cero.']);
        }

        $this->exigirQueQuepa($partida, $cantidad, $this->desglose($partida));

        return DB::transaction(function () use ($partida, $cantidad, $motivo, $userId): OrdenCompraDetalleCancelacion {
            $cancelacion = OrdenCompraDetalleCancelacion::create([
                'orden_compra_detalle_id' => $partida->getKey(),
                'cantidad' => $cantidad,
                'motivo' => $motivo,
                'estatus' => CancelacionUnidadesEstatus::Pendiente->value,
                'solicitado_por' => $userId ?? Auth::id(),
            ]);

            // Mientras esté pendiente, la orden se reporta pendiente de aprobación.
            $this->estado->recalcular($partida->ordenCompra);

            return $cancelacion;
        });
    }

    /**
     * El jefe de compras autoriza: aquí sí se cancelan las unidades.
     *
     * @throws ValidationException
     */
    public function autorizar(OrdenCompraDetalleCancelacion $cancelacion, ?string $userId = null): void
    {
        if ($cancelacion->estatus !== CancelacionUnidadesEstatus::Pendiente) {
            throw ValidationException::withMessages(['estatus' => 'Esta cancelación ya fue resuelta.']);
        }

        DB::transaction(function () use ($cancelacion, $userId): void {
            $partida = $cancelacion->detalle()->lockForUpdate()->firstOrFail();
            $orden = $partida->ordenCompra;
            $cantidad = (float) $cancelacion->cantidad;

            // Los topes se vuelven a medir al autorizar: entre la solicitud y
            // la firma pudo llegar material, o una factura que lo ampare.
            $this->exigirQueQuepa($partida, $cantidad, $this->desglose($partida, excluyendo: (int) $cancelacion->getKey()), alAutorizar: true);

            $partida->update([
                'cantidad_cancelada' => (float) $partida->cantidad_cancelada + $cantidad,
                'subtotal' => round(((float) $partida->cantidad - (float) $partida->cantidad_cancelada - $cantidad) * (float) $partida->precio_unitario, 2),
            ]);

            $this->bajarTotalDeLaOrden($partida, $cantidad);
            $this->revertirPresupuesto($partida, $cantidad, $cancelacion, $userId);

            $cancelacion->transitionTo(CancelacionUnidadesEstatus::Autorizada);
            $cancelacion->update([
                'autorizado_por' => $userId ?? Auth::id(),
                'autorizado_at' => now(),
            ]);

            $this->estado->recalcular($orden->fresh());
        });
    }

    /**
     * El jefe de compras rechaza: no se toca cantidad ni presupuesto.
     *
     * @throws ValidationException
     */
    public function rechazar(OrdenCompraDetalleCancelacion $cancelacion, string $motivo, ?string $userId = null): void
    {
        if ($cancelacion->estatus !== CancelacionUnidadesEstatus::Pendiente) {
            throw ValidationException::withMessages(['estatus' => 'Esta cancelación ya fue resuelta.']);
        }

        DB::transaction(function () use ($cancelacion, $motivo, $userId): void {
            $cancelacion->transitionTo(CancelacionUnidadesEstatus::Rechazada);
            $cancelacion->update([
                'motivo_rechazo' => $motivo,
                'autorizado_por' => $userId ?? Auth::id(),
                'autorizado_at' => now(),
            ]);

            $this->estado->recalcular($cancelacion->detalle->ordenCompra);
        });
    }

    /**
     * Importe con impuestos de una cantidad de la partida: lo mismo que baja
     * del total de la orden al autorizar, y lo mismo con lo que se compara el
     * saldo que las facturas dejan libre.
     */
    public function importeConImpuestos(OrdenCompraDetalle $partida, float $cantidad): float
    {
        $importe = round($cantidad * (float) $partida->precio_unitario, 2);

        return $partida->sin_impuestos ? $importe : round($importe * (1 + (float) config('costos.iva_rate')), 2);
    }

    /**
     * @param  array{recibido: float, recibido_facturado: float, sin_recibir: float, tope_facturas: ?float, cancelable: float, importe_sin_facturar: ?float}  $desglose
     *
     * @throws ValidationException
     */
    private function exigirQueQuepa(OrdenCompraDetalle $partida, float $cantidad, array $desglose, bool $alAutorizar = false): void
    {
        if ($cantidad > $desglose['sin_recibir'] + (float) config('costos.epsilon_cantidad')) {
            throw ValidationException::withMessages([
                'cantidad' => $alAutorizar
                    ? 'Ya no alcanza el saldo de la partida: llegó material despues de solicitar la cancelación.'
                    : sprintf(
                        'Sólo quedan %.2f %s por cancelar en la partida "%s".',
                        $desglose['sin_recibir'],
                        $partida->unidad,
                        $partida->descripcion,
                    ),
            ]);
        }

        // El importe se compara con la misma holgura con la que se acepta una
        // factura que se pasa por centavos del saldo de la orden: esa misma
        // factura no debe impedir cancelar lo que sí está libre.
        if ($desglose['importe_sin_facturar'] === null
            || $this->importeConImpuestos($partida, $cantidad) <= $desglose['importe_sin_facturar'] + $this->tolerancia()) {
            return;
        }

        throw ValidationException::withMessages([
            'cantidad' => sprintf(
                '%s de esta partida caben %.2f %s. Para cancelar más, cancele la factura que las ampara o registre su nota de crédito.',
                $alAutorizar
                    ? 'Llegó una factura que ampara esas unidades después de solicitar la cancelación: ahora sólo'
                    : 'Las facturas de la orden ya amparan parte de lo que no ha llegado: sin factura sólo',
                $desglose['cancelable'],
                $partida->unidad,
            ),
        ]);
    }

    /**
     * @return array{recibido: float, recibido_facturado: float, sin_recibir: float, tope_facturas: ?float, cancelable: float, importe_sin_facturar: ?float}
     */
    private function desgloseConSaldo(OrdenCompraDetalle $partida, ?float $importeSinFacturar, ?int $excluyendo = null): array
    {
        // Lo recibido en entradas vigentes, y de eso lo que entró con una
        // factura que sigue viva.
        $recepcion = EntregaDetalle::query()
            ->join('costos_entregas', 'costos_entregas.id', '=', 'costos_entrega_detalle.entrega_id')
            ->leftJoin('costos_facturas', function ($join): void {
                $join->on('costos_facturas.id', '=', 'costos_entregas.factura_id')
                    ->where('costos_facturas.estatus', '!=', FacturaEstatus::Cancelada->value);
            })
            ->where('costos_entrega_detalle.orden_compra_detalle_id', $partida->getKey())
            ->whereNull('costos_entregas.cancelada_at')
            ->selectRaw('COALESCE(SUM(costos_entrega_detalle.cantidad_recibida), 0) AS recibido')
            ->selectRaw('COALESCE(SUM(CASE WHEN costos_facturas.id IS NULL THEN 0 ELSE costos_entrega_detalle.cantidad_recibida END), 0) AS facturado')
            ->first();

        $recibido = (float) ($recepcion->recibido ?? 0);
        $recibidoFacturado = (float) ($recepcion->facturado ?? 0);

        $pendientes = (float) OrdenCompraDetalleCancelacion::query()
            ->where('orden_compra_detalle_id', $partida->getKey())
            ->when($excluyendo !== null, fn ($q) => $q->whereKeyNot($excluyendo))
            ->pendiente()
            ->sum('cantidad');

        $sinRecibir = max(0.0, (float) $partida->cantidad - (float) $partida->cantidad_cancelada - $recibido - $pendientes);

        $topeFacturas = $importeSinFacturar === null
            ? null
            : max(0.0, round($importeSinFacturar / $this->importeConImpuestos($partida, 1.0), 4));

        return [
            'recibido' => $recibido,
            'recibido_facturado' => $recibidoFacturado,
            'sin_recibir' => $sinRecibir,
            'tope_facturas' => $topeFacturas,
            'cancelable' => $topeFacturas === null ? $sinRecibir : min($sinRecibir, $topeFacturas),
            'importe_sin_facturar' => $importeSinFacturar,
        ];
    }

    /**
     * La holgura con la que se acepta una factura que se pasa por centavos del
     * saldo de la orden.
     */
    private function tolerancia(): float
    {
        return max(
            (float) config('costos.epsilon_monto'),
            (float) ConfiguracionCostos::actual()->tolerancia_recepcion,
        );
    }

    /**
     * Importe de la orden que ninguna factura viva ampara: el total menos lo
     * facturado (neto de notas de crédito) y menos lo que otras cancelaciones
     * pendientes ya van a bajar del total. Nulo cuando no hay facturas: ahí
     * nada limita y no vale la pena traducirlo a unidades.
     */
    private function importeSinFacturar(OrdenCompra $orden, ?int $excluyendo = null): ?float
    {
        $facturas = Factura::query()
            ->where('orden_compra_id', $orden->getKey())
            ->where('estatus', '!=', FacturaEstatus::Cancelada->value);

        if (! $facturas->exists()) {
            return null;
        }

        $facturado = (float) (clone $facturas)->sum('total');

        $notasCredito = (float) NotaCredito::query()
            ->whereIn('factura_id', (clone $facturas)->select('id'))
            ->where('estatus', NotaCreditoEstatus::Vigente->value)
            ->sum('monto');

        $tomadoPorPendientes = OrdenCompraDetalleCancelacion::query()
            ->with('detalle')
            ->whereHas('detalle', fn ($q) => $q->where('orden_compra_id', $orden->getKey()))
            ->when($excluyendo !== null, fn ($q) => $q->whereKeyNot($excluyendo))
            ->pendiente()
            ->get()
            ->sum(fn (OrdenCompraDetalleCancelacion $c): float => $this->importeConImpuestos($c->detalle, (float) $c->cantidad));

        return max(0.0, (float) $orden->total - ($facturado - $notasCredito) - $tomadoPorPendientes);
    }

    /**
     * El total de la orden baja por el importe cancelado. Se resta el delta en
     * lugar de recalcular el total desde las líneas: una orden capturada a mano
     * puede traer conceptos que no salen de las partidas, y recalcular los
     * borraría.
     */
    private function bajarTotalDeLaOrden(OrdenCompraDetalle $partida, float $cantidad): void
    {
        $orden = $partida->ordenCompra;

        $orden->update(['total' => max(0, round((float) $orden->total - $this->importeConImpuestos($partida, $cantidad), 2))]);
    }

    /**
     * Las unidades canceladas dejan de pesar en el presupuesto: reversa parcial
     * al tipo de cambio con el que la orden aplicó su cargo.
     *
     * Se permite sobregiro porque es una reversa —baja el comprometido— y una
     * partida sin centro de costos no mueve nada, igual que al aplicar el cargo.
     */
    private function revertirPresupuesto(OrdenCompraDetalle $partida, float $cantidad, OrdenCompraDetalleCancelacion $cancelacion, ?string $userId): void
    {
        if ($partida->obra_rubro_id === null) {
            return;
        }

        $orden = $partida->ordenCompra;

        $this->presupuesto->aplicarCargo(
            entrada: $orden,
            obraRubroId: (int) $partida->obra_rubro_id,
            monto: -round($cantidad * (float) $partida->precio_unitario, 2),
            estatus: RubroAfectadoEstatus::Aplicado,
            descripcion: "Cancelación de {$cantidad} {$partida->unidad} · OC {$orden->folio} · cancelación #{$cancelacion->getKey()}",
            userId: $userId ?? Auth::id(),
            allowSobregiro: true,
            moneda: (string) $orden->moneda,
            tc: (float) $orden->tipo_cambio,
        );
    }
}
