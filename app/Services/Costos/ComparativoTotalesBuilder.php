<?php

namespace App\Services\Costos;

use App\Enums\Costos\TipoFiscalPartida;
use App\Models\Costos\Requisicion;
use App\Support\Moneda;

/**
 * Totales del comparativo de una requisición: agrupa las opciones elegidas por
 * (proveedor, OC) y aplica {@see RetencionCalculator} por grupo (las retenciones
 * dependen del proveedor), igual que el resumen neto en pantalla. Devuelve el
 * subtotal, IVA, el desglose de retenciones acumulado y el neto a pagar.
 */
class ComparativoTotalesBuilder
{
    public function __construct(private RetencionCalculator $calculator) {}

    /**
     * @return array{
     *     subtotal: float,
     *     iva: float,
     *     retenciones: list<array{concepto: string, monto: float}>,
     *     total_retenciones: float,
     *     total: float,
     *     neto: float,
     *     moneda: string
     * }
     */
    public function build(Requisicion $requisicion): array
    {
        $grupos = [];
        $monedas = [];
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
                $monedas[] = $seleccion->cotizacionPrecio->moneda ?? 'mxn';
                $clave = $seleccion->proveedor_id.'|'.($seleccion->numero_oc ?? 1);
                $grupos[$clave] ??= ['proveedor' => $seleccion->proveedor, 'lineas' => []];
                $grupos[$clave]['lineas'][] = ['tipo_fiscal' => $tipoFiscal, 'subtotal' => $subtotal];
            }
        }

        $subtotal = 0.0;
        $iva = 0.0;
        $retenciones = [];
        foreach ($grupos as $grupo) {
            if (! $grupo['proveedor']) {
                continue;
            }
            $resultado = $this->calculator->calcular($grupo['proveedor'], $grupo['lineas']);
            $subtotal += $resultado['subtotal'];
            $iva += $resultado['iva'];
            foreach ($resultado['retenciones'] as $retencion) {
                $retenciones[$retencion['clave']] ??= ['concepto' => $retencion['concepto'], 'monto' => 0.0];
                $retenciones[$retencion['clave']]['monto'] += $retencion['monto'];
            }
        }

        $subtotal = round($subtotal, 2);
        $iva = round($iva, 2);
        $desglose = array_map(
            fn (array $r) => ['concepto' => $r['concepto'], 'monto' => round($r['monto'], 2)],
            array_values($retenciones),
        );
        $totalRetenciones = round(array_sum(array_column($desglose, 'monto')), 2);

        return [
            'subtotal' => $subtotal,
            'iva' => $iva,
            'retenciones' => $desglose,
            'total_retenciones' => $totalRetenciones,
            'total' => round($subtotal + $iva, 2),
            'neto' => round($subtotal + $iva - $totalRetenciones, 2),
            'moneda' => Moneda::agregada($monedas),
        ];
    }
}
