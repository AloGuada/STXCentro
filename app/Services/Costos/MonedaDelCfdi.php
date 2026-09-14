<?php

namespace App\Services\Costos;

use App\Models\Costos\ConfiguracionCostos;
use App\Models\Costos\OrdenCompra;
use Illuminate\Support\Carbon;

/**
 * Lleva un CFDI a la moneda de su orden de compra.
 *
 * El proveedor puede cotizar en dólares y timbrar en pesos (o al revés). La
 * factura se guarda siempre en la moneda de la orden, porque contra ella se
 * miden el saldo facturable, los porcentajes y los pagos; lo que dijo el CFDI
 * queda al lado en `moneda_cfdi`, `total_cfdi` y `tipo_cambio_cfdi`.
 *
 * `tipo_cambio_cfdi` es siempre **pesos por unidad de la moneda de la orden**
 * según esa factura. Es el que usa el kardex para costear en pesos:
 *
 *  - misma moneda: el `TipoCambio` del XML (1 si todo es en pesos);
 *  - orden en pesos y CFDI en divisa: el `TipoCambio` del XML convierte los
 *    importes, y la tasa contra la orden es 1;
 *  - orden en divisa y CFDI en otra moneda: el XML no dice cuánto vale la
 *    moneda de la orden, así que sale de dividir el total del CFDI entre lo que
 *    la orden esperaba cobrar: lo que está entrando al recibir en almacén, o el
 *    saldo por facturar de la orden fuera de ahí (una factura por orden).
 *
 * Ese cociente cuadra con cualquier total, así que solo, no distingue el tipo
 * de cambio que pactó el proveedor del XML de otra entrega. Por eso se
 * contrasta con el FIX de Banxico del día de la factura, con la tolerancia en
 * por ciento de Configuración de Costos (`tolerancia_tipo_cambio`).
 */
class MonedaDelCfdi
{
    /** Los importes del CFDI que se reexpresan en la moneda de la orden. */
    private const IMPORTES = ['subtotal', 'total', 'iva_trasladado', 'iva_retenido', 'isr_retenido'];

    public function __construct(private readonly TipoCambioService $tiposDeCambio) {}

    /**
     * Mensaje si el CFDI no se puede llevar a la moneda de la orden, o null.
     *
     * @param  array<string, mixed>  $fiscal
     * @param  ?float  $esperado  lo que la orden espera cobrar por lo que ampara, en su moneda
     */
    public function error(OrdenCompra $orden, array $fiscal, ?float $esperado = null): ?string
    {
        $cfdi = $this->monedaCfdi($fiscal);
        $deOrden = $this->monedaOrden($orden);

        if ($cfdi === $deOrden) {
            return null;
        }

        if ($cfdi !== 'mxn' && empty($fiscal['tipo_cambio'])) {
            return sprintf('El CFDI viene en %s pero no trae TipoCambio: no se puede convertir a la moneda de la orden.', strtoupper($cfdi));
        }

        if ($deOrden === 'mxn') {
            return null;
        }

        $esperado ??= (float) $orden->saldo_facturable;
        $totalCfdi = (float) ($fiscal['total'] ?? 0);

        if ($esperado <= 0 || $totalCfdi <= 0) {
            return sprintf(
                'La factura viene en %s y la orden en %s, y la orden no tiene saldo por facturar contra el cual calcular el tipo de cambio.',
                strtoupper($cfdi),
                strtoupper($deOrden),
            );
        }

        return $this->errorDeTipoDeCambio($orden, $fiscal, $this->pesosPorUnidadDeLaOrden($fiscal, $esperado));
    }

    /**
     * El CFDI con sus importes en la moneda de la orden y los datos originales
     * en `moneda_cfdi`, `total_cfdi` y `tipo_cambio_cfdi`. Asume que
     * {@see error()} no se quejó.
     *
     * @param  array<string, mixed>  $fiscal
     * @return array<string, mixed>
     */
    public function aMonedaDeLaOrden(OrdenCompra $orden, array $fiscal, ?float $esperado = null): array
    {
        $cfdi = $this->monedaCfdi($fiscal);
        $deOrden = $this->monedaOrden($orden);
        $totalCfdi = round((float) ($fiscal['total'] ?? 0), 2);

        $convertido = [
            ...$fiscal,
            'moneda_cfdi' => $cfdi,
            'total_cfdi' => $totalCfdi,
        ];

        if ($cfdi === $deOrden) {
            return [...$convertido, 'tipo_cambio_cfdi' => $cfdi === 'mxn' ? 1.0 : ($fiscal['tipo_cambio'] ?? null)];
        }

        if ($deOrden === 'mxn') {
            return [
                ...$this->escalar($convertido, (float) $fiscal['tipo_cambio']),
                'tipo_cambio_cfdi' => 1.0,
            ];
        }

        $esperado ??= (float) $orden->saldo_facturable;

        return [
            ...$this->escalar($convertido, $esperado / $totalCfdi),
            'tipo_cambio_cfdi' => $this->pesosPorUnidadDeLaOrden($fiscal, $esperado),
        ];
    }

    /**
     * Cuántos pesos vale cada unidad de la moneda de la orden según el CFDI:
     * su total en pesos (el suyo, o convertido con su TipoCambio si vino en
     * una tercera divisa) entre lo que la orden esperaba cobrar.
     *
     * @param  array<string, mixed>  $fiscal
     */
    private function pesosPorUnidadDeLaOrden(array $fiscal, float $esperado): float
    {
        $pesosPorUnidadCfdi = $this->monedaCfdi($fiscal) === 'mxn' ? 1.0 : (float) $fiscal['tipo_cambio'];

        return round((float) $fiscal['total'] * $pesosPorUnidadCfdi / $esperado, 6);
    }

    /**
     * @param  array<string, mixed>  $fiscal
     */
    private function errorDeTipoDeCambio(OrdenCompra $orden, array $fiscal, float $deducido): ?string
    {
        $tolerancia = (float) ConfiguracionCostos::actual()->tolerancia_tipo_cambio;
        $fecha = ! empty($fiscal['fecha_factura']) ? Carbon::parse($fiscal['fecha_factura']) : null;
        $fix = $this->tiposDeCambio->mxnPorUnidad($this->monedaOrden($orden), $fecha);

        if ($fix <= 0) {
            return null;
        }

        $desvio = abs($deducido / $fix - 1) * 100;

        if ($desvio <= $tolerancia + 0.0001) {
            return null;
        }

        return sprintf(
            'El tipo de cambio que resulta de esta factura (%s) se aleja %s%% del FIX de Banxico del día de la factura (%s); la tolerancia configurada es %s%%. '
            .'Verifica que el CFDI ampare exactamente lo que se está facturando de esta orden.',
            number_format($deducido, 4),
            number_format($desvio, 1),
            number_format($fix, 4),
            rtrim(rtrim(number_format($tolerancia, 2), '0'), '.'),
        );
    }

    /**
     * @param  array<string, mixed>  $fiscal
     * @return array<string, mixed>
     */
    private function escalar(array $fiscal, float $factor): array
    {
        foreach (self::IMPORTES as $campo) {
            $fiscal[$campo] = round((float) ($fiscal[$campo] ?? 0) * $factor, 2);
        }

        return $fiscal;
    }

    /**
     * @param  array<string, mixed>  $fiscal
     */
    private function monedaCfdi(array $fiscal): string
    {
        return strtolower((string) ($fiscal['moneda'] ?? 'mxn'));
    }

    private function monedaOrden(OrdenCompra $orden): string
    {
        return strtolower((string) ($orden->moneda ?: 'mxn'));
    }
}
