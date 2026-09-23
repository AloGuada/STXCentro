<?php

namespace App\Services\Costos;

use App\Enums\Costos\AprobacionEstatus;
use App\Enums\Costos\CancelacionUnidadesEstatus;
use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\NotaCreditoEstatus;
use App\Enums\Costos\OrdenCompraEstatus;
use App\Enums\Costos\PagoEstatus;
use App\Enums\Costos\RubroAfectadoEstatus;
use App\Enums\Costos\SolicitudPagoEstatus;
use App\Models\Costos\ConfiguracionCostos;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\Factura;
use App\Models\Costos\NotaCredito;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\OrdenCompraDetalleCancelacion;
use App\Models\Costos\Pago;
use App\Models\Costos\SolicitudPago;
use Illuminate\Support\Collection;
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
 * - **Lo que nadie ampara.** Una factura puede adelantarse al material (el
 *   proveedor la sube al portal antes de surtir), y una orden de contado se
 *   paga con su solicitud de pago antes de recibir nada. Esas unidades no se
 *   han recibido, pero ya se cobraron o ya se pagaron: cancelarlas dejaría a la
 *   orden facturada o pagada por más de lo que pidió. El tope es el importe de
 *   la orden que no ampara ni una factura viva ni una solicitud con dinero ya
 *   comprometido, traducido a unidades de la partida.
 *
 * Una solicitud de pago que todavía no tiene dinero en juego no frena nada: al
 * autorizar, su monto baja solo lo que se pase del nuevo total de la orden
 * (ver {@see ajustarSolicitudesDePago()}).
 *
 * Nada surte efecto sin la autorización del jefe de compras: `solicitar()`
 * sólo deja el registro pendiente —y eso ya reporta la orden como pendiente de
 * aprobación—, y es `autorizar()` quien escribe la cantidad, baja el total,
 * ajusta las solicitudes de pago y revierte el presupuesto.
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
     * - `tope_amparado`: unidades que caben en el importe de la orden que no
     *   ampara ni una factura ni una solicitud de pago con dinero en juego.
     *   Nulo cuando no hay ninguna de las dos.
     * - `cancelable`: el menor de `sin_recibir` y `tope_amparado`.
     * - `importe_libre`: ese importe en dinero, con impuestos.
     * - `amparado_por`: `factura` o `pago`, lo que más ampara de la orden.
     *
     * Lo recibido se cuenta **bruto**, sin descontar devoluciones, igual que el
     * tope de {@see RegistradorRecepcion::validarSaldos()}: si aquí se contara
     * el neto, una devolución abriría espacio para cancelar unidades que sí
     * llegaron.
     *
     * @return array{recibido: float, recibido_facturado: float, sin_recibir: float, tope_amparado: ?float, cancelable: float, importe_libre: ?float, amparado_por: ?string}
     */
    public function desglose(OrdenCompraDetalle $partida, ?int $excluyendo = null): array
    {
        return $this->desgloseConAmparo(
            $partida,
            $this->amparo($partida->ordenCompra, $excluyendo),
            $excluyendo,
        );
    }

    /**
     * El desglose de cada partida de la orden, indexado por id de partida. Lo
     * que amparan facturas y solicitudes es de la orden entera y se mide una
     * sola vez.
     *
     * @return array<int, array{recibido: float, recibido_facturado: float, sin_recibir: float, tope_amparado: ?float, cancelable: float, importe_libre: ?float, amparado_por: ?string}>
     */
    public function desglosePorOrden(OrdenCompra $orden): array
    {
        $amparo = $this->amparo($orden);

        return $orden->detalles
            ->mapWithKeys(fn (OrdenCompraDetalle $partida): array => [
                (int) $partida->getKey() => $this->desgloseConAmparo($partida, $amparo),
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
     * Devuelve las solicitudes de pago que se ajustaron para no pasarse del
     * nuevo total de la orden.
     *
     * @return Collection<int, SolicitudPago>
     *
     * @throws ValidationException
     */
    public function autorizar(OrdenCompraDetalleCancelacion $cancelacion, ?string $userId = null): Collection
    {
        if ($cancelacion->estatus !== CancelacionUnidadesEstatus::Pendiente) {
            throw ValidationException::withMessages(['estatus' => 'Esta cancelación ya fue resuelta.']);
        }

        return DB::transaction(function () use ($cancelacion, $userId): Collection {
            $partida = $cancelacion->detalle()->lockForUpdate()->firstOrFail();
            $orden = $partida->ordenCompra;
            $cantidad = (float) $cancelacion->cantidad;
            $userId ??= Auth::id() ?? $cancelacion->solicitado_por;

            // Los topes se vuelven a medir al autorizar: entre la solicitud y
            // la firma pudo llegar material, una factura o un pago que lo
            // ampare.
            $this->exigirQueQuepa($partida, $cantidad, $this->desglose($partida, excluyendo: (int) $cancelacion->getKey()), alAutorizar: true);

            $partida->update([
                'cantidad_cancelada' => (float) $partida->cantidad_cancelada + $cantidad,
                'subtotal' => round(((float) $partida->cantidad - (float) $partida->cantidad_cancelada - $cantidad) * (float) $partida->precio_unitario, 2),
            ]);

            $this->bajarTotalDeLaOrden($partida, $cantidad);
            $ajustadas = $this->ajustarSolicitudesDePago($orden->fresh(), $cancelacion, $userId);
            $this->revertirPresupuesto($partida, $cantidad, $cancelacion, $userId);

            $cancelacion->transitionTo(CancelacionUnidadesEstatus::Autorizada);
            $cancelacion->update([
                'autorizado_por' => $userId,
                'autorizado_at' => now(),
            ]);

            $this->estado->recalcular($orden->fresh());

            return $ajustadas;
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
     * importe que facturas y solicitudes dejan libre.
     */
    public function importeConImpuestos(OrdenCompraDetalle $partida, float $cantidad): float
    {
        $importe = round($cantidad * (float) $partida->precio_unitario, 2);

        return $partida->sin_impuestos ? $importe : round($importe * (1 + (float) config('costos.iva_rate')), 2);
    }

    /**
     * Una solicitud de pago se puede ajustar mientras no haya dinero en juego:
     * sigue viva, no se ha pagado y su pago —si ya se programó— no se ha
     * partido en parcialidades ni tiene nada pagado.
     */
    public function solicitudAjustable(SolicitudPago $solicitud): bool
    {
        if (! in_array($solicitud->estatus, [SolicitudPagoEstatus::Borrador, SolicitudPagoEstatus::PendienteFirma, SolicitudPagoEstatus::Aprobada], true)) {
            return false;
        }

        return ! Pago::query()
            ->where('pagable_type', SolicitudPago::class)
            ->where('pagable_id', $solicitud->getKey())
            ->where(fn ($q) => $q
                ->whereIn('estatus', [PagoEstatus::Pagado->value, PagoEstatus::Parcial->value])
                ->orWhereNotNull('pago_padre_id'))
            ->exists();
    }

    /**
     * @param  array{recibido: float, recibido_facturado: float, sin_recibir: float, tope_amparado: ?float, cancelable: float, importe_libre: ?float, amparado_por: ?string}  $desglose
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
        if ($desglose['importe_libre'] === null
            || $this->importeConImpuestos($partida, $cantidad) <= $desglose['importe_libre'] + $this->tolerancia()) {
            return;
        }

        $porPago = $desglose['amparado_por'] === 'pago';

        throw ValidationException::withMessages([
            'cantidad' => sprintf(
                '%s de esta partida caben %.2f %s. Para cancelar más, %s.',
                match (true) {
                    $alAutorizar && $porPago => 'Se pagó parte de la orden después de solicitar la cancelación: ahora sólo',
                    $alAutorizar => 'Llegó una factura que ampara esas unidades después de solicitar la cancelación: ahora sólo',
                    $porPago => 'La solicitud de pago de la orden ya tiene dinero pagado o en parcialidades: sin pagar sólo',
                    default => 'Las facturas de la orden ya amparan parte de lo que no ha llegado: sin factura sólo',
                },
                $desglose['cancelable'],
                $partida->unidad,
                $porPago
                    ? 'hay que resolver con el proveedor la devolución de lo pagado'
                    : 'cancele la factura que las ampara o registre su nota de crédito',
            ),
        ]);
    }

    /**
     * @param  array{libre: ?float, por: ?string}  $amparo
     * @return array{recibido: float, recibido_facturado: float, sin_recibir: float, tope_amparado: ?float, cancelable: float, importe_libre: ?float, amparado_por: ?string}
     */
    private function desgloseConAmparo(OrdenCompraDetalle $partida, array $amparo, ?int $excluyendo = null): array
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

        $topeAmparado = $amparo['libre'] === null
            ? null
            : max(0.0, round($amparo['libre'] / $this->importeConImpuestos($partida, 1.0), 4));

        return [
            'recibido' => $recibido,
            'recibido_facturado' => $recibidoFacturado,
            'sin_recibir' => $sinRecibir,
            'tope_amparado' => $topeAmparado,
            'cancelable' => $topeAmparado === null ? $sinRecibir : min($sinRecibir, $topeAmparado),
            'importe_libre' => $amparo['libre'],
            'amparado_por' => $amparo['por'],
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
     * Importe de la orden que nadie ampara: el total menos lo que ya está
     * comprometido con el proveedor y menos lo que otras cancelaciones
     * pendientes ya van a bajar del total.
     *
     * Lo comprometido es el **mayor** —no la suma— de lo facturado (neto de
     * notas de crédito) y lo que amparan las solicitudes de pago con dinero en
     * juego: en contado se paga primero y la factura llega después por el mismo
     * importe, así que sumarlos contaría doble.
     *
     * `libre` es nulo cuando ni facturas ni solicitudes amparan nada: ahí nada
     * limita y no vale la pena traducirlo a unidades.
     *
     * @return array{libre: ?float, por: ?string}
     */
    private function amparo(OrdenCompra $orden, ?int $excluyendo = null): array
    {
        $facturas = Factura::query()
            ->where('orden_compra_id', $orden->getKey())
            ->where('estatus', '!=', FacturaEstatus::Cancelada->value);

        $hayFacturas = $facturas->exists();
        $facturado = 0.0;

        if ($hayFacturas) {
            $notasCredito = (float) NotaCredito::query()
                ->whereIn('factura_id', (clone $facturas)->select('id'))
                ->where('estatus', NotaCreditoEstatus::Vigente->value)
                ->sum('monto');

            $facturado = (float) (clone $facturas)->sum('total') - $notasCredito;
        }

        $solicitudesConDinero = $this->solicitudesVivas($orden)->reject(fn (SolicitudPago $s): bool => $this->solicitudAjustable($s));
        $pagado = (float) $solicitudesConDinero->sum('monto_total');

        if (! $hayFacturas && $solicitudesConDinero->isEmpty()) {
            return ['libre' => null, 'por' => null];
        }

        $tomadoPorPendientes = OrdenCompraDetalleCancelacion::query()
            ->with('detalle')
            ->whereHas('detalle', fn ($q) => $q->where('orden_compra_id', $orden->getKey()))
            ->when($excluyendo !== null, fn ($q) => $q->whereKeyNot($excluyendo))
            ->pendiente()
            ->get()
            ->sum(fn (OrdenCompraDetalleCancelacion $c): float => $this->importeConImpuestos($c->detalle, (float) $c->cantidad));

        return [
            'libre' => max(0.0, (float) $orden->total - max($facturado, $pagado) - $tomadoPorPendientes),
            'por' => $pagado > $facturado ? 'pago' : 'factura',
        ];
    }

    /**
     * @return Collection<int, SolicitudPago>
     */
    private function solicitudesVivas(OrdenCompra $orden): Collection
    {
        return $orden->solicitudesPago()
            ->where('estatus', '!=', SolicitudPagoEstatus::Cancelada->value)
            ->orderByDesc('id')
            ->get();
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
     * Las solicitudes de pago de la orden no pueden pedir más que su nuevo
     * total. Lo que se pasen se descuenta de las que no tienen dinero en juego,
     * empezando por la más reciente —en parcialidades, la última en pagarse—.
     * La que queda en cero se cancela.
     *
     * Sólo se descuenta el excedente: si las solicitudes ya pedían menos que el
     * total (un anticipo parcial), cancelar unidades no las toca.
     *
     * @return Collection<int, SolicitudPago>
     *
     * @throws ValidationException
     */
    private function ajustarSolicitudesDePago(OrdenCompra $orden, OrdenCompraDetalleCancelacion $cancelacion, ?string $userId): Collection
    {
        $vivas = $this->solicitudesVivas($orden);
        $exceso = round((float) $vivas->sum('monto_total') - (float) $orden->total, 2);
        $ajustadas = collect();
        $epsilon = (float) config('costos.epsilon_monto');

        foreach ($vivas as $solicitud) {
            if ($exceso <= $epsilon) {
                break;
            }

            if (! $this->solicitudAjustable($solicitud)) {
                continue;
            }

            $monto = (float) $solicitud->monto_total;
            $baja = min($exceso, $monto);
            $nuevo = round($monto - $baja, 2);

            if ($nuevo <= $epsilon) {
                $this->cancelarSolicitud($solicitud, $cancelacion, $userId);
            } else {
                $this->reducirSolicitud($solicitud, $nuevo);
            }

            $exceso = round($exceso - $baja, 2);
            $ajustadas->push($solicitud->fresh());
        }

        // El tope ya impide llegar aquí: lo que no se puede ajustar cuenta como
        // amparado. Se revisa por si algo se pagó a media transacción.
        if ($exceso > $epsilon) {
            throw ValidationException::withMessages([
                'cantidad' => 'Las solicitudes de pago de la orden ya tienen dinero pagado por más del nuevo total.',
            ]);
        }

        return $ajustadas;
    }

    /**
     * Baja el monto de la solicitud, sus renglones en la misma proporción y el
     * pago que ya tenga programado.
     */
    private function reducirSolicitud(SolicitudPago $solicitud, float $nuevo): void
    {
        $factor = $nuevo / (float) $solicitud->monto_total;

        foreach ($solicitud->detalles as $detalle) {
            $detalle->update(['subtotal' => round((float) $detalle->subtotal * $factor, 2)]);
        }

        $solicitud->update(['monto_total' => $nuevo]);

        $this->pagosProgramados($solicitud)->each(fn (Pago $pago) => $pago->update([
            'monto_pago' => $nuevo,
            'monto_mxn' => $pago->monto_mxn === null ? null : round($nuevo * (float) $pago->tipo_cambio, 2),
        ]));
    }

    /**
     * La solicitud se queda sin nada que pagar: se cancela igual que desde su
     * pantalla —aprobaciones pendientes fuera de la bandeja— y su pago
     * programado también. No pasa por el rechazo, que cancelaría la orden.
     */
    private function cancelarSolicitud(SolicitudPago $solicitud, OrdenCompraDetalleCancelacion $cancelacion, ?string $userId): void
    {
        $solicitud->cadenaAprobacion()
            ->where('estatus', AprobacionEstatus::Pendiente->value)
            ->update([
                'estatus' => AprobacionEstatus::Cancelada->value,
                'fecha_respuesta' => now(),
            ]);

        $this->pagosProgramados($solicitud)->each(fn (Pago $pago) => $pago->transitionTo(PagoEstatus::Cancelado));

        $solicitud->transitionTo(SolicitudPagoEstatus::Cancelada);
        $solicitud->registrarCancelacion(
            "Cancelación de unidades #{$cancelacion->getKey()} de la OC: la orden ya no tiene saldo que pagar con esta solicitud.",
            (string) $userId,
        );
    }

    /**
     * @return Collection<int, Pago>
     */
    private function pagosProgramados(SolicitudPago $solicitud): Collection
    {
        return Pago::query()
            ->where('pagable_type', SolicitudPago::class)
            ->where('pagable_id', $solicitud->getKey())
            ->whereNull('pago_padre_id')
            ->whereIn('estatus', [PagoEstatus::Pendiente->value, PagoEstatus::Programado->value])
            ->get();
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
