<?php

namespace App\Services\Portal;

use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\OrdenCompraEstatus;
use App\Models\Costos\ConfiguracionCostos;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\Pago;
use App\Models\Media;
use App\Models\Proveedor;
use App\Support\Moneda;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Arma el tablero del proveedor: una fila por factura, anidada bajo su orden de
 * compra, con los documentos que puede abrir y lo que puede hacer en cada etapa.
 *
 * Todo se devuelve como arrays planos a propósito. `OrdenCompra` tiene diez
 * accessors en `$appends` y varios consultan la base al serializarse, así que
 * pasar los modelos a Inertia convertiría una pantalla en cientos de queries.
 */
class TableroProveedorBuilder
{
    public const TABS = ['activas', 'completadas'];

    private const POR_PAGINA = 20;

    public static function tabValido(?string $tab): string
    {
        return in_array($tab, self::TABS, true) ? $tab : 'activas';
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function ordenes(Proveedor $proveedor, string $tab): LengthAwarePaginator
    {
        $comprobantePermitido = ConfiguracionCostos::actual()->comprobanteHoyPermitido();

        return $this->aplicarTab($this->base($proveedor), $tab)
            ->with([
                // Las canceladas siguen a la vista (el proveedor necesita el rastro
                // de lo que subió), pero no cuentan para el facturado.
                'facturas' => fn ($q) => $q->orderBy('fecha_factura')->orderBy('id')
                    ->with([
                        'mediaPdf',
                        'mediaXml',
                        'mediaComprobanteRecepcion',
                        'notasCredito',
                        'anticiposAplicados:id,factura_id,monto',
                        'pagoRaiz.media',
                        'pagoRaiz.pagosParciales.media',
                    ]),
                'detalles:id,orden_compra_id,descripcion,unidad,cantidad,cantidad_cancelada,precio_unitario,subtotal',
            ])
            ->latest('id')
            ->paginate(self::POR_PAGINA)
            ->withQueryString()
            ->through(fn (OrdenCompra $oc) => $this->mapearOrden($oc, $comprobantePermitido));
    }

    /**
     * @return array{activas: int, completadas: int}
     */
    public function conteos(Proveedor $proveedor): array
    {
        return [
            'activas' => $this->aplicarTab($this->base($proveedor), 'activas')->count(),
            'completadas' => $this->aplicarTab($this->base($proveedor), 'completadas')->count(),
        ];
    }

    /**
     * Totales de toda la cuenta, no de la página en pantalla.
     *
     * @return array{facturado: float, pagado: float, pendiente: float, moneda: string, facturas: int, facturas_pagadas: int}
     */
    public function resumen(Proveedor $proveedor): array
    {
        $facturas = Factura::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('estatus', '!=', FacturaEstatus::Cancelada->value)
            ->get(['id', 'total', 'moneda', 'estatus']);

        $facturado = round((float) $facturas->sum('total'), 2);
        $pagadas = $facturas->where('estatus', FacturaEstatus::Pagada);
        $pagado = round((float) $pagadas->sum('total'), 2);

        return [
            'facturado' => $facturado,
            'pagado' => $pagado,
            'pendiente' => round($facturado - $pagado, 2),
            'moneda' => Moneda::agregada($facturas->pluck('moneda')),
            'facturas' => $facturas->count(),
            'facturas_pagadas' => $pagadas->count(),
        ];
    }

    /**
     * @return Builder<OrdenCompra>
     */
    private function base(Proveedor $proveedor): Builder
    {
        return OrdenCompra::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('estatus', '!=', OrdenCompraEstatus::Cancelada->value);
    }

    /**
     * Completada = pagada y facturada por completo. El estatus `pagada` ya
     * significa "todas las facturas pagadas" (lo mantiene OrdenCompraEstadoService),
     * pero no garantiza que se haya facturado el total de la orden: una OC de 100
     * con una sola factura de 50 ya pagada sigue teniendo saldo por facturar y
     * pertenece a Activas.
     *
     * @param  Builder<OrdenCompra>  $query
     * @return Builder<OrdenCompra>
     */
    private function aplicarTab(Builder $query, string $tab): Builder
    {
        $tabla = (new OrdenCompra)->getTable();
        $facturado = "(select coalesce(sum(f.total), 0) from costos_facturas f
            where f.orden_compra_id = {$tabla}.id and f.estatus <> '".FacturaEstatus::Cancelada->value."')";
        $epsilon = (float) config('costos.epsilon_monto');

        if ($tab === 'completadas') {
            return $query
                ->where('estatus', OrdenCompraEstatus::Pagada->value)
                ->whereRaw("{$facturado} >= {$tabla}.total - ?", [$epsilon]);
        }

        return $query->where(fn (Builder $q) => $q
            ->where('estatus', '!=', OrdenCompraEstatus::Pagada->value)
            ->orWhereRaw("{$facturado} < {$tabla}.total - ?", [$epsilon]));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapearOrden(OrdenCompra $oc, bool $comprobantePermitido): array
    {
        $total = (float) $oc->total;
        $facturado = $this->sumaFacturada($oc->facturas);
        $saldoFacturable = round($total - $facturado, 2);
        $epsilon = (float) config('costos.epsilon_monto');

        return [
            'id' => $oc->id,
            'folio' => $oc->folio,
            'fecha' => $oc->created_at?->toDateString(),
            'fecha_entrega_esperada' => $oc->fecha_entrega_esperada?->toDateString(),
            'total' => $total,
            'moneda' => $oc->moneda,
            'total_facturado' => $facturado,
            'saldo_facturable' => max(0.0, $saldoFacturable),
            'completada' => $oc->estatus === OrdenCompraEstatus::Pagada && $saldoFacturable <= $epsilon,
            'puede_facturar' => $saldoFacturable > $epsilon,
            'partidas' => $oc->detalles->map(fn ($d) => [
                'id' => $d->id,
                'descripcion' => $d->descripcion,
                'unidad' => $d->unidad,
                'cantidad' => (float) $d->cantidad,
                'precio_unitario' => (float) $d->precio_unitario,
                'subtotal' => (float) $d->subtotal,
            ])->values()->all(),
            'facturas' => $oc->facturas
                ->map(fn (Factura $f) => $this->mapearFactura($f, $comprobantePermitido))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapearFactura(Factura $factura, bool $comprobantePermitido): array
    {
        $cancelada = $factura->estatus === FacturaEstatus::Cancelada;
        $pago = $factura->pagoRaiz;
        $comprobante = $factura->mediaComprobanteRecepcion;

        // Se calcula aquí, con las relaciones ya cargadas: los accessors
        // `monto_notas_credito` / `saldo_facturado` consultan por factura.
        $notasVigentes = round(
            (float) $factura->notasCredito
                ->filter(fn ($nc) => ($nc->estatus instanceof \BackedEnum ? $nc->estatus->value : $nc->estatus) === 'vigente')
                ->sum('monto'),
            2,
        );
        $anticipos = round((float) $factura->anticiposAplicados->sum('monto'), 2);

        return [
            'id' => $factura->id,
            'folio' => $factura->folio,
            'fecha' => $factura->fecha_factura?->toDateString(),
            'total' => (float) $factura->total,
            'moneda' => $factura->moneda,
            'estatus' => $factura->estatus?->value,
            'uuid_fiscal' => $factura->uuid_fiscal,
            'folio_fiscal' => $factura->folio_fiscal,
            'subtotal' => (float) $factura->subtotal,
            'monto_notas_credito' => $notasVigentes,
            'monto_anticipos' => $anticipos,
            'saldo_facturado' => max(0.0, round((float) $factura->total - $anticipos - $notasVigentes, 2)),
            'pago' => $pago ? [
                'id' => $pago->id,
                'folio' => $pago->folio,
                'estatus' => $pago->estatus?->value,
                'monto' => (float) $pago->monto_pago,
                'fecha_programada' => $pago->fecha_pago_programada?->toDateString(),
                'fecha_realizada' => $pago->fecha_pago_realizada?->toDateString(),
                'parcialidades' => $pago->pagosParciales->map(fn (Pago $p) => [
                    'id' => $p->id,
                    'folio' => $p->folio,
                    'numero' => $p->numero_parcialidad,
                    'monto' => (float) $p->monto_pago,
                    'estatus' => $p->estatus?->value,
                    'fecha_programada' => $p->fecha_pago_programada?->toDateString(),
                    'fecha_realizada' => $p->fecha_pago_realizada?->toDateString(),
                    'comprobante_url' => $this->urlMedia($p->media),
                ])->values()->all(),
            ] : null,
            'cancelada' => $cancelada,
            'pagada' => $factura->estatus === FacturaEstatus::Pagada,
            'pdf_url' => $this->urlMedia($factura->mediaPdf),
            'xml_url' => $this->urlMedia($factura->mediaXml),
            'recepcion' => $comprobante ? [
                'url' => $this->urlMedia($comprobante),
                'nombre' => $comprobante->nombre_original,
                'fecha' => $comprobante->created_at?->toDateString(),
            ] : null,
            'puede_subir_recepcion' => ! $cancelada
                && $factura->estatus === FacturaEstatus::PendienteRecepcion
                && $comprobantePermitido,
            // Sin pago programado el contrarecibo saldría sin fecha, que es el
            // dato que el proveedor busca: la celda dice "Por programar".
            'contrarecibo_url' => $pago?->fecha_pago_programada !== null
                ? route('portal.facturas.contrarecibo', $factura)
                : null,
            'comprobantes_pago' => $this->comprobantesDePago($pago),
            'notas_credito' => $factura->notasCredito->map(fn ($nc) => [
                'id' => $nc->id,
                'folio' => $nc->folio,
                'monto' => (float) $nc->monto,
                'estatus' => $nc->estatus instanceof \BackedEnum ? $nc->estatus->value : $nc->estatus,
                'fecha' => $nc->fecha_emision?->toDateString(),
            ])->values()->all(),
        ];
    }

    /**
     * Comprobantes del pago: el del pago raíz, o el de cada parcialidad cuando
     * el pago se partió.
     *
     * @return list<array<string, mixed>>
     */
    private function comprobantesDePago(?Pago $pago): array
    {
        if ($pago === null) {
            return [];
        }

        $pagos = $pago->pagosParciales->isNotEmpty()
            ? $pago->pagosParciales
            : collect([$pago]);

        return $pagos
            ->filter(fn (Pago $p) => $p->media !== null)
            ->map(fn (Pago $p) => [
                'id' => $p->id,
                'folio' => $p->folio,
                'monto' => (float) $p->monto_pago,
                'fecha' => $p->fecha_pago_realizada?->toDateString(),
                'numero_parcialidad' => $p->numero_parcialidad,
                'url' => $this->urlMedia($p->media),
            ])
            ->values()
            ->all();
    }

    private function urlMedia(?Media $media): ?string
    {
        return $media ? route('portal.media.show', $media) : null;
    }

    /**
     * @param  Collection<int, Factura>  $facturas
     */
    private function sumaFacturada(Collection $facturas): float
    {
        return round(
            (float) $facturas
                ->reject(fn (Factura $f) => $f->estatus === FacturaEstatus::Cancelada)
                ->sum('total'),
            2,
        );
    }
}
