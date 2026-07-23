<?php

namespace App\Services\Costos;

use App\Enums\Costos\TipoFiscalPartida;
use App\Models\Costos\Requisicion;

/**
 * Totales del comparativo de una requisición, desglosados por divisa: agrupa
 * las opciones elegidas por (proveedor, OC, moneda) y aplica
 * {@see RetencionCalculator} por grupo (las retenciones dependen del
 * proveedor), igual que el resumen neto en pantalla. Devuelve un bloque
 * (subtotal, IVA, retenciones, total, neto) por cada divisa presente y, cuando
 * hay exactamente una divisa extranjera con tipo de cambio capturado, el neto
 * combinado en MXN. Las partidas "solo cotización" no suman.
 */
class ComparativoTotalesBuilder
{
    public function __construct(private RetencionCalculator $calculator) {}

    /**
     * @return array{
     *     bloques: list<array{
     *         moneda: string,
     *         subtotal: float,
     *         iva: float,
     *         retenciones: list<array{concepto: string, monto: float}>,
     *         total_retenciones: float,
     *         total: float,
     *         neto: float
     *     }>,
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

        $bloques = [];
        foreach ($porMoneda as $moneda => $datos) {
            $subtotal = round($datos['subtotal'], 2);
            $iva = round($datos['iva'], 2);
            $desglose = array_map(
                fn (array $r) => ['concepto' => $r['concepto'], 'monto' => round($r['monto'], 2)],
                array_values($datos['retenciones']),
            );
            $totalRetenciones = round(array_sum(array_column($desglose, 'monto')), 2);
            $bloques[] = [
                'moneda' => $moneda,
                'subtotal' => $subtotal,
                'iva' => $iva,
                'retenciones' => $desglose,
                'total_retenciones' => $totalRetenciones,
                'total' => round($subtotal + $iva, 2),
                'neto' => round($subtotal + $iva - $totalRetenciones, 2),
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
            'divisa' => count($divisas) === 1 ? $divisas[0]['moneda'] : null,
            'multi_divisa' => count($divisas) > 1,
            'tc' => $tc,
            'neto_mxn' => $netoMxn,
        ];
    }
}
