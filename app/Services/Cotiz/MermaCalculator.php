<?php

namespace App\Services\Cotiz;

use App\Models\Cotiz\GeneradoraRegistro;

/**
 * Aplica la fórmula de merma a un registro para obtener sus kilos con merma.
 * Port de `evaluarMerma` / `kilosConMerma` de prepsim.
 *
 * Semántica de merma (distinta de la evaluación estricta): las variables nulas o no
 * finitas se sustituyen por 0, y si la fórmula falla (división por cero, etc.) el
 * resultado cae a 0 — así un dato faltante no propaga NaN por toda la grilla.
 */
class MermaCalculator
{
    public function __construct(private readonly FormulaEvaluator $evaluator) {}

    /**
     * Kilos con merma de un registro de generadora. Si el registro no tiene fórmula de
     * merma, devuelve sus kilos reales tal cual.
     */
    public function aplicar(GeneradoraRegistro $registro): float
    {
        $kilosReales = $registro->kilos_reales ?? 0.0;
        $formula = $registro->merma?->formula;

        if ($formula === null || trim($formula) === '') {
            return $kilosReales;
        }

        return $this->evaluar($formula, [
            't_ml_m2' => $registro->t_ml_m2,
            'kilos_reales' => $kilosReales,
            'ancho' => $registro->ancho,
            'largo' => $registro->largo,
            'cantidad' => $registro->cantidad,
            'cant_pzas' => $registro->cant_pzas,
            'peso_lineal' => $registro->materialOrigen?->peso_lineal,
            'peso_default' => $registro->materialOrigen?->peso_default,
        ]);
    }

    /**
     * Evalúa una fórmula de merma contra un contexto, sustituyendo nulos/no-finitos por 0
     * y cayendo a 0 si la fórmula falla.
     *
     * @param  array<string, float|int|string|null>  $contexto
     */
    public function evaluar(string $formula, array $contexto): float
    {
        $limpio = [];
        foreach ($contexto as $clave => $valor) {
            $limpio[$clave] = (is_numeric($valor) && is_finite((float) $valor)) ? (float) $valor : 0.0;
        }

        return $this->evaluator->evaluar($formula, $limpio) ?? 0.0;
    }
}
