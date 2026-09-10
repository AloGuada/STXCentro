<?php

namespace App\Services\Qal;

/**
 * Espesores de pintura por SSPC-PA2.
 *
 * Cada medición son tres lecturas del calibre y el espesor de la pieza es el
 * promedio de esos promedios. Una medición por debajo del 80 % del requerido se
 * anota pero **no rechaza**: lo que decide es el promedio final contra el
 * requerido. Es la regla del formato y la que el inspector ya conoce.
 */
class CalculadorEspesores
{
    public const MEDICIONES_MIN = 5;

    public const MEDICIONES_MAX = 15;

    public const LECTURAS_POR_MEDICION = 3;

    /** Por debajo de esta fracción del requerido, la medición se avisa. */
    private const UMBRAL_MEDICION = 0.8;

    /**
     * Sólo cuentan las mediciones a la vista: el formato arranca con cinco y el
     * inspector añade hasta quince, pero una columna que ocultó no se promedia
     * aunque conserve números.
     *
     * @param  array<int, array<int, float|int|string|null>>  $lecturas  medición (desde 1) → lecturas en mils
     * @return array{promedio: float|null, cumple: bool|null, bajas: list<int>}
     */
    public function resumir(array $lecturas, int $visibles, ?float $requerido): array
    {
        $promedios = [];
        $bajas = [];

        foreach ($lecturas as $medicion => $valores) {
            if ($medicion > $visibles) {
                continue;
            }

            $capturadas = array_values(array_filter($valores, fn (mixed $valor): bool => is_numeric($valor)));

            if ($capturadas === []) {
                continue;
            }

            $promedio = array_sum(array_map('floatval', $capturadas)) / count($capturadas);
            $promedios[] = $promedio;

            if ($requerido !== null && $requerido > 0 && $promedio < self::UMBRAL_MEDICION * $requerido) {
                $bajas[] = (int) $medicion;
            }
        }

        if ($promedios === []) {
            return ['promedio' => null, 'cumple' => null, 'bajas' => $bajas];
        }

        $promedio = array_sum($promedios) / count($promedios);

        return [
            'promedio' => round($promedio, 2),
            'cumple' => $requerido === null ? null : $promedio >= $requerido,
            'bajas' => $bajas,
        ];
    }
}
