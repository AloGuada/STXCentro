<?php

namespace App\Services\Cotiz;

use App\Models\Cotiz\Obra;

/**
 * Derivaciones de la Cuadrilla Global de Montaje. Reemplaza en PHP la vista
 * `v_obra_cuadrilla_global` de prepsim (EXPL. M.O rows 152-162).
 *
 * Total personas = Σ(cantidad_por_grupo) × num_grupos.
 * Nómina semanal = Σ(cantidad_por_grupo × sueldo_semanal) × num_grupos.
 */
class CuadrillaGlobalDerivations
{
    /** Sobrecosto de contratista aplicado a la nómina (la vista original usa 1.15 literal). */
    private const FACTOR_CONTRATISTA = 1.15;

    /** Código de la categoría que se excluye al contar personas para viáticos (Excel C12). */
    private const CODIGO_CABO = 'CABO';

    /**
     * @return array{
     *     num_grupos: int,
     *     personas_por_grupo: int,
     *     personas_totales: int,
     *     nomina_por_grupo: float,
     *     nomina_total: float,
     *     nomina_con_contratista: float,
     *     personas_viaticos: int
     * }
     */
    public function totales(Obra $obra): array
    {
        $numGrupos = max(1, (int) $obra->num_grupos);

        $personasPorGrupo = 0;
        $nominaPorGrupo = 0.0;
        $cabosPorGrupo = 0;

        foreach ($obra->cuadrillaGlobal as $fila) {
            $cantidad = (int) $fila->cantidad_por_grupo;
            $sueldo = (float) ($fila->categoria?->sueldo_semanal ?? 0.0);
            $personasPorGrupo += $cantidad;
            $nominaPorGrupo += $cantidad * $sueldo;
            if ($fila->categoria?->codigo === self::CODIGO_CABO) {
                $cabosPorGrupo += $cantidad;
            }
        }

        $personasTotales = $personasPorGrupo * $numGrupos;
        $nominaTotal = $nominaPorGrupo * $numGrupos;

        return [
            'num_grupos' => $numGrupos,
            'personas_por_grupo' => $personasPorGrupo,
            'personas_totales' => $personasTotales,
            'nomina_por_grupo' => $nominaPorGrupo,
            'nomina_total' => $nominaTotal,
            'nomina_con_contratista' => $nominaTotal * self::FACTOR_CONTRATISTA,
            'personas_viaticos' => $personasTotales - $cabosPorGrupo * $numGrupos,
        ];
    }
}
