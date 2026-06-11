<?php

namespace App\Services\Cotiz;

/**
 * Área pintable (m²) por registro de tarjeta. Port de `src/lib/pintura.ts` (M030).
 *
 * Regla: solo acero no galvanizado se pinta. Según el tipo de sección la fórmula
 * (editable en el catálogo `cotiz_pintura_formulas`) usa las variables:
 *   kg, peso_lineal, lado (pulgadas), peralte (m), patin (m).
 * lado/peralte se parsean de la descripción del insumo; patin ≈ 0.4 × peralte.
 *
 * Semántica de evaluación = la de las mermas (error → 0), igual que prepsim.
 */
class PinturaCalculator
{
    /** Aceros NO estructurales (no se pintan), por palabra en la descripción. */
    private const NO_ESTRUCTURAL = '/\b(pija|tornillo|tapcon|perno|taquete|clip|clousure|sellador|silicon|cinta|pintura|thiner|solvente|dry fog|redondo|lamina|lámina|losacero|varilla|solera|polin|polín)\b/i';

    public function __construct(private readonly FormulaEvaluator $evaluator) {}

    /**
     * Heurística para `tipo_pintura = 'auto'`: decide la clave por la descripción.
     */
    public function inferirTipo(?string $descripcion): string
    {
        if ($descripcion === null || $descripcion === '') {
            return 'no_pinta';
        }

        $d = mb_strtolower($descripcion);

        if (str_contains($d, 'galv')) {
            return 'no_pinta';
        }
        if (preg_match(self::NO_ESTRUCTURAL, $d) === 1) {
            return 'no_pinta';
        }
        if (preg_match('/\bhss\b/i', $d) === 1 || str_starts_with($d, 'aps') || preg_match('/\baps\b/i', $d) === 1) {
            return 'hss';
        }
        if (preg_match('/\bir\s+\d/i', $d) === 1 || preg_match('/\bipr\b/i', $d) === 1 || preg_match('/\bw\d/i', $d) === 1) {
            return 'ipr';
        }
        if (str_starts_with($d, 'placa') || str_contains($d, ' placa ')) {
            return 'placa';
        }

        return 'no_pinta';
    }

    /**
     * Extrae el lado en pulgadas para HSS/APS. Null si no parsea.
     */
    public function extraerLadoPulgadas(string $descripcion): ?float
    {
        if (preg_match('/HSS\s+de\s+(\d+(?:\s+\d+\/\d+)?(?:\/\d+)?)\s*"/i', $descripcion, $m) === 1) {
            $v = $this->parseFraccion($m[1]);
            if ($v !== null) {
                return $v;
            }
        }
        if (preg_match('/APS\s+\S+\s+x\s+(\d+(?:\s+\d+\/\d+)?(?:\/\d+)?)\s*"/i', $descripcion, $m) === 1) {
            $v = $this->parseFraccion($m[1]);
            if ($v !== null) {
                return $v;
            }
        }

        return null;
    }

    /**
     * Extrae el peralte en metros para IPR/W. Null si no parsea.
     */
    public function extraerPeralteMetros(string $descripcion): ?float
    {
        if (preg_match('/\bIR\s+(\d+)/i', $descripcion, $m) === 1) {
            return (float) $m[1] / 1000;
        }
        if (preg_match('/\bIPR\s+(\d+)/i', $descripcion, $m) === 1) {
            return (float) $m[1] / 1000;
        }
        if (preg_match('/\bW(\d+)\s*[Xx]/i', $descripcion, $m) === 1) {
            return (float) $m[1] * 0.0254;
        }

        return null;
    }

    /**
     * Área pintable (m²) de un registro.
     *
     * @param  array<string, string>  $formulas  Mapa clave → fórmula (catálogo de pintura).
     */
    public function area(
        ?string $tipoCrudo,
        ?float $kilos,
        ?float $pesoLineal,
        ?string $descripcion,
        array $formulas,
    ): float {
        $kg = $kilos ?? 0.0;
        $pl = $pesoLineal ?? 0.0;

        if ($kg == 0.0 || $pl <= 0.0) {
            return 0.0;
        }

        $clave = ($tipoCrudo === null || $tipoCrudo === '' || $tipoCrudo === 'auto')
            ? $this->inferirTipo($descripcion)
            : $tipoCrudo;

        $formula = $formulas[$clave] ?? null;
        if ($formula === null || trim($formula) === '') {
            return 0.0;
        }

        $lado = $this->extraerLadoPulgadas($descripcion ?? '') ?? 0.0;
        $peralte = $this->extraerPeralteMetros($descripcion ?? '') ?? 0.0;
        $patin = $peralte * 0.4;

        return $this->evaluator->evaluar($formula, [
            'kg' => $kg,
            'peso_lineal' => $pl,
            'lado' => $lado,
            'peralte' => $peralte,
            'patin' => $patin,
        ]) ?? 0.0;
    }

    /**
     * Parsea "6", "1/2", "1 1/2", "3/4". Null si no parsea.
     */
    private function parseFraccion(string $s): ?float
    {
        $trimmed = trim($s);
        if (! str_contains($trimmed, '/')) {
            return is_numeric($trimmed) ? (float) $trimmed : null;
        }

        $total = 0.0;
        foreach (preg_split('/\s+/', $trimmed) as $parte) {
            if (str_contains($parte, '/')) {
                [$num, $den] = array_pad(explode('/', $parte), 2, null);
                if (! is_numeric($num) || ! is_numeric($den) || (float) $den === 0.0) {
                    return null;
                }
                $total += (float) $num / (float) $den;
            } else {
                if (! is_numeric($parte)) {
                    return null;
                }
                $total += (float) $parte;
            }
        }

        return $total;
    }
}
