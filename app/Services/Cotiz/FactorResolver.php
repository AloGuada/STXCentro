<?php

namespace App\Services\Cotiz;

use Closure;

/**
 * Resuelve las cantidades de todos los factores de una tarjeta en una pasada
 * multi-iteración (DAG acíclico). Port de `evaluarFactores` de prepsim (`src/lib/formulas.ts`).
 *
 * Variables disponibles en cada fórmula:
 * - Las claves de `$variablesBase` (típicamente `kg_fab`, `area_pintura`).
 * - El código de cualquier otro factor ya evaluado en esta misma resolución.
 *
 * Factores manuales (`formula` NULL) toman su valor de `$cantidadesManuales[codigo]` (default 0).
 *
 * Si una fórmula referencia un factor aún no resuelto, se reintenta hasta 5 pasadas, lo que
 * cubre cualquier DAG razonable. Un factor que nunca resuelve (ciclo o error persistente) cae a 0.
 *
 * El `$expandir` opcional resuelve direcciones semánticas (`total.tarjeta.kg`,
 * `tarjeta.factor[cod=X]`, …) antes de evaluar — se conecta en Fase 3 (subsistema M046). Una
 * dirección que lance (ciclo, instancia inexistente) deja la fórmula sin resolver en esa pasada,
 * igual que una variable aún no definida.
 */
class FactorResolver
{
    private const MAX_PASADAS = 5;

    public function __construct(private readonly FormulaEvaluator $evaluator) {}

    /**
     * @param  list<array{codigo: string, formula: string|null}>  $factores
     * @param  array<string, float|int>  $variablesBase
     * @param  array<string, float|int>  $cantidadesManuales
     * @param  (Closure(string, array<string, float>): array{formula: string, vars: array<string, float>})|null  $expandir
     * @return array<string, float>
     */
    public function resolver(
        array $factores,
        array $variablesBase,
        array $cantidadesManuales = [],
        ?Closure $expandir = null,
    ): array {
        $valores = [];

        for ($pasada = 0; $pasada < self::MAX_PASADAS; $pasada++) {
            $cambios = 0;

            foreach ($factores as $factor) {
                $codigo = $factor['codigo'];

                if (array_key_exists($codigo, $valores)) {
                    continue;
                }

                if ($factor['formula'] === null) {
                    $valores[$codigo] = (float) ($cantidadesManuales[$codigo] ?? 0);
                    $cambios++;

                    continue;
                }

                [$formula, $varsExpandidas] = $expandir !== null
                    ? array_values($this->expandirSeguro($expandir, $factor['formula'], $valores))
                    : [$factor['formula'], []];

                if ($formula === null) {
                    continue;
                }

                $contexto = [...$variablesBase, ...$varsExpandidas, ...$valores];
                $resultado = $this->evaluator->evaluar($formula, $contexto);

                if ($resultado !== null) {
                    $valores[$codigo] = $resultado;
                    $cambios++;
                }
            }

            if ($cambios === 0) {
                break;
            }
        }

        foreach ($factores as $factor) {
            $valores[$factor['codigo']] ??= 0.0;
        }

        return $valores;
    }

    /**
     * Ejecuta el expansor de direcciones semánticas tolerando fallos: si lanza
     * (ciclo, instancia inexistente) la fórmula se deja sin resolver en esta pasada.
     *
     * @param  Closure(string, array<string, float>): array{formula: string, vars: array<string, float>}  $expandir
     * @param  array<string, float>  $valores
     * @return array{formula: string|null, vars: array<string, float>}
     */
    private function expandirSeguro(Closure $expandir, string $formula, array $valores): array
    {
        try {
            return $expandir($formula, $valores);
        } catch (\Throwable) {
            return ['formula' => null, 'vars' => []];
        }
    }
}
