<?php

namespace App\Services\Cotiz;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Parser;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Throwable;

/**
 * Evaluador aislado de fórmulas de usuario para el módulo Cotización.
 *
 * Envuelve symfony/expression-language para que el motor de cálculo nunca
 * dependa directamente del paquete. Port de la lógica de prepsim (`src/lib/formulas.ts`,
 * antes basada en expr-eval): error de evaluación → null (no 0), de modo que un
 * resolvedor multi-pasada pueda distinguir "aún no resoluble" de "vale 0".
 *
 * Notas de portabilidad expr-eval → Symfony EL:
 * - La potencia es `**` (en Symfony `^` es XOR). Normalizar `^`→`**` al portar seeds.
 * - `%` es módulo en ambos.
 * - Solo se exponen variables escalares y las funciones registradas aquí; el evaluador
 *   no permite acceso a objetos ni métodos de las variables.
 */
class FormulaEvaluator
{
    private ExpressionLanguage $expressionLanguage;

    public function __construct()
    {
        $this->expressionLanguage = new ExpressionLanguage;
        $this->registrarFunciones();
    }

    /**
     * Evalúa una fórmula escalar contra un mapa de variables.
     *
     * Devuelve null si la fórmula falla (variable indefinida, sintaxis, división por
     * cero o resultado no finito como NaN/INF).
     *
     * @param  array<string, float|int|null>  $variables
     */
    public function evaluar(string $formula, array $variables = []): ?float
    {
        if (trim($formula) === '') {
            return null;
        }

        try {
            $resultado = $this->expressionLanguage->evaluate($formula, $variables);
        } catch (Throwable) {
            return null;
        }

        if (! is_int($resultado) && ! is_float($resultado)) {
            return null;
        }

        $valor = (float) $resultado;

        return is_finite($valor) ? $valor : null;
    }

    /**
     * Valida la sintaxis de una fórmula sin evaluarla.
     *
     * Devuelve null si es válida, o el mensaje de error (útil para feedback en el
     * editor de fórmulas de factores/mermas/pintura). No exige que las variables
     * referenciadas existan; solo comprueba sintaxis y funciones conocidas.
     */
    public function validar(string $formula): ?string
    {
        if (trim($formula) === '') {
            return null;
        }

        try {
            $this->expressionLanguage->lint(
                $formula,
                [],
                Parser::IGNORE_UNKNOWN_VARIABLES,
            );

            return null;
        } catch (SyntaxError $e) {
            return $e->getMessage();
        }
    }

    private function registrarFunciones(): void
    {
        $this->registrar('roundup', function (float $x, int $decimales = 0): float {
            $factor = 10 ** $decimales;

            return ($x <=> 0) * ceil(abs($x) * $factor) / $factor;
        });

        $this->registrar('ceil', fn (float $x): float => ceil($x));
        $this->registrar('floor', fn (float $x): float => floor($x));
        $this->registrar('abs', fn (float $x): float => abs($x));
        $this->registrar('round', fn (float $x, int $decimales = 0): float => round($x, $decimales));
    }

    /**
     * Registra una función disponible en las fórmulas. El compilador no se usa
     * (solo evaluamos, nunca compilamos a PHP) pero la firma del paquete lo exige.
     */
    private function registrar(string $nombre, callable $evaluador): void
    {
        $this->expressionLanguage->register(
            $nombre,
            fn (...$args): string => sprintf('%s(%s)', $nombre, implode(', ', $args)),
            fn (array $variables, ...$args) => $evaluador(...$args),
        );
    }
}
