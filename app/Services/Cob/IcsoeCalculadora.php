<?php

namespace App\Services\Cob;

use App\Enums\Cob\IcsoeMetodo;
use Carbon\CarbonInterface;

/**
 * Aritmética del ICSOE: desglose mensual del periodo de obra y totales de mano
 * de obra estimada, real y riesgo en cuotas. Puro, sin base de datos.
 *
 * Porta las fórmulas del bosquejo original del usuario (siroc_pro_tracker.tsx);
 * si cambia una fórmula aquí, hay que revisar `resources/js/components/cob/icsoe-calculos.ts`,
 * que replica lo mismo para el feedback optimista del formulario.
 */
class IcsoeCalculadora
{
    /**
     * Cuotas obrero-patronales distintas del riesgo de trabajo, como porcentaje
     * fijo del salario base. La prima de riesgo de la empresa se le suma encima.
     */
    public const CARGAS_SOCIALES_BASE = 26.0;

    /**
     * Días del periodo repartidos por mes calendario. Ambos extremos cuentan,
     * igual que en el bosquejo (89 días para 2023-02-06 → 2023-05-05).
     *
     * @return array{total_dias: int, meses: list<array{anio: int, mes: int, dias_proyecto: int, sbc: float}>}
     */
    public function desglose(CarbonInterface $inicio, CarbonInterface $fin, SbcResolver $sbc): array
    {
        $cursor = $inicio->copy()->startOfDay();
        $limite = $fin->copy()->startOfDay();

        if ($cursor->greaterThan($limite)) {
            return ['total_dias' => 0, 'meses' => []];
        }

        $meses = [];
        $totalDias = 0;

        while ($cursor->lessThanOrEqualTo($limite)) {
            $finDelMes = $cursor->copy()->endOfMonth()->startOfDay();
            $corte = $finDelMes->lessThan($limite) ? $finDelMes : $limite;

            // Aritmética entera sobre el día del mes: ambos extremos caen en el
            // mismo mes, así que restar los días evita `diffInDays` (float en
            // Carbon 3 y sensible a la hora).
            $dias = $corte->day - $cursor->day + 1;
            $totalDias += $dias;

            $meses[] = [
                'anio' => $cursor->year,
                'mes' => $cursor->month,
                'dias_proyecto' => $dias,
                'sbc' => $sbc->sbc($cursor->year),
            ];

            // startOfMonth antes de avanzar: 31 de enero + 1 mes se saltaría febrero.
            $cursor = $cursor->copy()->startOfMonth()->addMonthNoOverflow();
        }

        return ['total_dias' => $totalDias, 'meses' => $meses];
    }

    /**
     * Meta de mano de obra a comprobar ante el IMSS.
     */
    public function moEstimadaTotal(
        IcsoeMetodo $metodo,
        float $montoBase,
        float $porcentajeMo,
        ?float $superficieM2,
        ?float $costoM2,
    ): float {
        return match ($metodo) {
            IcsoeMetodo::Superficie => ($superficieM2 ?? 0) * ($costoM2 ?? 0),
            IcsoeMetodo::Porcentaje => $montoBase * ($porcentajeMo / 100),
        };
    }

    public function moEstimadaDiaria(float $moEstimadaTotal, int $totalDias): float
    {
        return $totalDias > 0 ? $moEstimadaTotal / $totalDias : 0.0;
    }

    public function moReal(float $diasCotizados, float $sbcAplicado): float
    {
        return $diasCotizados * $sbcAplicado;
    }

    /**
     * Diferencia contra el total exacto, NO contra la suma de las metas
     * mensuales: por redondeo esas dos cifras difieren en centavos y el
     * bosquejo usa el total. No "arreglar" esto sin revisar el test.
     */
    public function diferenciaMo(float $moEstimadaTotal, float $moRealTotal): float
    {
        return $moEstimadaTotal - $moRealTotal;
    }

    /**
     * Estimación de lo que el IMSS cobraría sobre la mano de obra no comprobada.
     */
    public function montoRiesgo(float $diferenciaMo, float $primaRiesgo): float
    {
        if ($diferenciaMo <= 0) {
            return 0.0;
        }

        return $diferenciaMo * ((self::CARGAS_SOCIALES_BASE + $primaRiesgo) / 100);
    }
}
