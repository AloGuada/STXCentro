<?php

namespace App\Services\Costos;

use App\Enums\Costos\TipoFiscalPartida;
use App\Models\Costos\Requisicion;
use Illuminate\Support\Collection;

/**
 * Totales del comparativo de una requisición, desglosados por divisa: agrupa
 * las opciones elegidas por (proveedor, OC, moneda) y aplica
 * {@see RetencionCalculator} por grupo (las retenciones dependen del
 * proveedor), igual que el resumen neto en pantalla. Las partidas "solo
 * cotización" suman al subtotal/IVA/total con su precio de referencia (mejor
 * proveedor o menor cotizado), pero como no se surten en las OCs se restan del
 * neto a pagar. Cuando hay exactamente una divisa extranjera con tipo de
 * cambio capturado, incluye además el neto combinado en MXN.
 */
class ComparativoTotalesBuilder
{
    public function __construct(
        private RetencionCalculator $calculator,
        private BuscadorMejorProveedor $buscador,
    ) {}

    /**
     * @return array{
     *     bloques: list<array{
     *         moneda: string,
     *         subtotal: float,
     *         iva: float,
     *         retenciones: list<array{concepto: string, monto: float}>,
     *         total_retenciones: float,
     *         total: float,
     *         solo_cotizacion: float,
     *         neto: float
     *     }>,
     *     referencias: array<int, array{importe: float, moneda: string}>,
     *     divisa: string|null,
     *     multi_divisa: bool,
     *     tc: float,
     *     neto_mxn: float|null
     * }
     */
    public function build(Requisicion $requisicion): array
    {
        $grupos = [];
        foreach ($requisicion->detalles as $detalle) {
            if ($detalle->solo_cotizacion) {
                continue;
            }

            $tipoFiscal = $detalle->tipo_fiscal instanceof TipoFiscalPartida
                ? $detalle->tipo_fiscal->value
                : (string) ($detalle->tipo_fiscal ?? TipoFiscalPartida::Mercancia->value);

            foreach ($detalle->selecciones as $seleccion) {
                $subtotal = (float) ($seleccion->cotizacionPrecio->precio_unitario ?? 0) * (float) $seleccion->cantidad;
                if ($subtotal <= 0) {
                    continue;
                }
                $moneda = strtolower($seleccion->cotizacionPrecio->moneda ?? 'mxn');
                $clave = $seleccion->proveedor_id.'|'.($seleccion->numero_oc ?? 1).'|'.$moneda;
                $grupos[$clave] ??= ['proveedor' => $seleccion->proveedor, 'moneda' => $moneda, 'lineas' => []];
                $grupos[$clave]['lineas'][] = ['tipo_fiscal' => $tipoFiscal, 'subtotal' => $subtotal];
            }
        }

        $porMoneda = [];
        foreach ($grupos as $grupo) {
            if (! $grupo['proveedor']) {
                continue;
            }
            $resultado = $this->calculator->calcular($grupo['proveedor'], $grupo['lineas']);
            $moneda = $grupo['moneda'];
            $porMoneda[$moneda] ??= ['subtotal' => 0.0, 'iva' => 0.0, 'retenciones' => []];
            $porMoneda[$moneda]['subtotal'] += $resultado['subtotal'];
            $porMoneda[$moneda]['iva'] += $resultado['iva'];
            foreach ($resultado['retenciones'] as $retencion) {
                $porMoneda[$moneda]['retenciones'][$retencion['clave']] ??= ['concepto' => $retencion['concepto'], 'monto' => 0.0];
                $porMoneda[$moneda]['retenciones'][$retencion['clave']]['monto'] += $retencion['monto'];
            }
        }

        [$referenciaPorMoneda, $referencias] = $this->referenciasSoloCotizacion($requisicion);

        $bloques = [];
        foreach (array_unique([...array_keys($porMoneda), ...array_keys($referenciaPorMoneda)]) as $moneda) {
            $subtotalOc = round($porMoneda[$moneda]['subtotal'] ?? 0.0, 2);
            $ivaOc = round($porMoneda[$moneda]['iva'] ?? 0.0, 2);
            $refSubtotal = round($referenciaPorMoneda[$moneda] ?? 0.0, 2);
            $refIva = round($refSubtotal * 0.16, 2);
            $desglose = array_map(
                fn (array $r) => ['concepto' => $r['concepto'], 'monto' => round($r['monto'], 2)],
                array_values($porMoneda[$moneda]['retenciones'] ?? []),
            );
            $totalRetenciones = round(array_sum(array_column($desglose, 'monto')), 2);
            $bloques[] = [
                'moneda' => $moneda,
                'subtotal' => round($subtotalOc + $refSubtotal, 2),
                'iva' => round($ivaOc + $refIva, 2),
                'retenciones' => $desglose,
                'total_retenciones' => $totalRetenciones,
                'total' => round($subtotalOc + $refSubtotal + $ivaOc + $refIva, 2),
                'solo_cotizacion' => round($refSubtotal + $refIva, 2),
                'neto' => round($subtotalOc + $ivaOc - $totalRetenciones, 2),
            ];
        }

        usort(
            $bloques,
            fn (array $a, array $b) => (($a['moneda'] === 'mxn') <=> ($b['moneda'] === 'mxn'))
                ?: strcmp($a['moneda'], $b['moneda']),
        );

        $divisas = array_values(array_filter($bloques, fn (array $b) => $b['moneda'] !== 'mxn'));
        $tc = (float) ($requisicion->tipo_cambio ?? 0);

        $netoMxn = null;
        if (count($divisas) === 1 && $tc > 0) {
            $netoMxn = round(
                array_sum(array_map(
                    fn (array $b) => $b['moneda'] === 'mxn' ? $b['neto'] : $b['neto'] * $tc,
                    $bloques,
                )),
                2,
            );
        }

        return [
            'bloques' => $bloques,
            'referencias' => $referencias,
            'divisa' => count($divisas) === 1 ? $divisas[0]['moneda'] : null,
            'multi_divisa' => count($divisas) > 1,
            'tc' => $tc,
            'neto_mxn' => $netoMxn,
        ];
    }

    /**
     * Importe de referencia de cada partida "solo cotización": precio del mejor
     * proveedor global si la cotizó, si no el menor precio cotizado (misma
     * regla best-case que el comparativo en pantalla), por la cantidad
     * solicitada.
     *
     * @return array{0: array<string, float>, 1: array<int, array{importe: float, moneda: string}>}
     */
    private function referenciasSoloCotizacion(Requisicion $requisicion): array
    {
        $soloCotizacion = $requisicion->detalles
            ->filter(fn ($d) => $d->solo_cotizacion && $d->cotizaciones->contains(fn ($c) => (float) $c->precio_unitario > 0));

        if ($soloCotizacion->isEmpty()) {
            return [[], []];
        }

        $mejorId = $this->buscador->buscar($requisicion)['id'] ?? null;

        $porMoneda = [];
        $porDetalle = [];
        foreach ($soloCotizacion as $detalle) {
            /** @var Collection $conPrecio */
            $conPrecio = $detalle->cotizaciones->filter(fn ($c) => (float) $c->precio_unitario > 0);
            $delMejor = $mejorId ? $conPrecio->where('proveedor_id', $mejorId) : collect();
            $cot = ($delMejor->isNotEmpty() ? $delMejor : $conPrecio)
                ->sortBy(fn ($c) => (float) $c->precio_unitario)
                ->first();

            $importe = (float) $cot->precio_unitario * (float) $detalle->cantidad;
            $moneda = strtolower($cot->moneda ?? 'mxn');
            $porMoneda[$moneda] = ($porMoneda[$moneda] ?? 0.0) + $importe;
            $porDetalle[$detalle->id] = ['importe' => round($importe, 2), 'moneda' => $moneda];
        }

        return [$porMoneda, $porDetalle];
    }
}
