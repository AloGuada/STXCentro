<?php

namespace App\Services\Costos;

use App\Enums\Costos\TipoFiscalPartida;
use App\Models\Proveedor;
use Illuminate\Support\Collection;

/**
 * Calcula el desglose de retenciones (ISR/IVA) de una orden de compra a partir
 * del proveedor (tipo de persona + régimen) y el tipo fiscal de cada línea.
 * El cálculo es informativo (se corrobora al recibir la factura) y se computa
 * al vuelo, por lo que recalcula si cambia régimen, tipo de partida o monto.
 */
class RetencionCalculator
{
    /**
     * @param  Collection<int, array{tipo_fiscal: string, subtotal: float}>|array<int, array{tipo_fiscal: string, subtotal: float}>  $lineas
     * @return array{
     *     subtotal: float,
     *     iva: float,
     *     total_neto: float,
     *     retenciones: list<array{clave: string, concepto: string, tasa: float, base: float, monto: float}>
     * }
     */
    public function calcular(Proveedor $proveedor, Collection|array $lineas): array
    {
        $lineas = collect($lineas);
        $tasas = config('costos.retenciones');
        $ivaRate = (float) config('costos.iva_rate');

        $esPF = $proveedor->esPersonaFisica();
        $esResico = $esPF && $proveedor->esResico();

        $subtotal = round((float) $lineas->sum('subtotal'), 2);
        $iva = round($subtotal * $ivaRate, 2);

        // Acumula retenciones por clave para presentarlas agrupadas.
        $acumulado = [];
        $add = function (string $clave, string $concepto, float $tasa, float $base) use (&$acumulado) {
            if ($base <= 0 || $tasa <= 0) {
                return;
            }
            if (! isset($acumulado[$clave])) {
                $acumulado[$clave] = ['clave' => $clave, 'concepto' => $concepto, 'tasa' => $tasa, 'base' => 0.0, 'monto' => 0.0];
            }
            $acumulado[$clave]['base'] += $base;
            $acumulado[$clave]['monto'] += $base * $tasa;
        };

        foreach ($lineas as $linea) {
            $tipo = $linea['tipo_fiscal'] ?? TipoFiscalPartida::Mercancia->value;
            $base = (float) ($linea['subtotal'] ?? 0);

            // ISR RESICO: 1.25% sobre el 100% de CADA línea (PF en RESICO).
            // Excluyente con ISR Honorarios (se omite el 10% en líneas de servicio).
            if ($esResico) {
                $add('isr_resico', 'ISR RESICO', (float) $tasas['isr_resico'], $base);
            }

            // ISR Fletes: 4% sobre línea de flete (PF y PM).
            if ($tipo === TipoFiscalPartida::Flete->value) {
                $add('isr_fletes', 'ISR Fletes', (float) $tasas['isr_fletes'], $base);
            }

            // Servicio profesional (honorarios): solo PF.
            if ($tipo === TipoFiscalPartida::ServicioProfesional->value && $esPF) {
                if (! $esResico) {
                    $add('isr_honorarios', 'ISR Honorarios', (float) $tasas['isr_honorarios'], $base);
                }
                $add('iva_honorarios', 'IVA Honorarios', (float) $tasas['iva_honorarios'], $base);
            }

            // Renta de bienes: solo PF.
            if ($tipo === TipoFiscalPartida::Renta->value && $esPF) {
                $add('iva_renta', 'IVA Renta', (float) $tasas['iva_renta'], $base);
            }
        }

        $retenciones = collect($acumulado)
            ->map(fn (array $r) => [
                'clave' => $r['clave'],
                'concepto' => $r['concepto'],
                'tasa' => $r['tasa'],
                'base' => round($r['base'], 2),
                'monto' => round($r['monto'], 2),
            ])
            ->values()
            ->all();

        $totalRetenciones = round(array_sum(array_column($retenciones, 'monto')), 2);

        return [
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total_neto' => round($subtotal + $iva - $totalRetenciones, 2),
            'retenciones' => $retenciones,
        ];
    }
}
