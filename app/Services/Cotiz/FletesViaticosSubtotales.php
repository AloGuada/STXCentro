<?php

namespace App\Services\Cotiz;

use App\Models\Cotiz\Obra;

/**
 * Subtotales de Fletes y Viáticos por grupo. Reemplaza en PHP la vista
 * `v_obra_fletes_viaticos_subtotal` (importe = cantidad × p_unit, agregado por grupo).
 */
class FletesViaticosSubtotales
{
    /**
     * @return array{subtotales: array<string, float>, total: float}
     */
    public function calcular(Obra $obra): array
    {
        $obra->loadMissing('fletesViaticos');

        $subtotales = [];
        $total = 0.0;
        foreach ($obra->fletesViaticos as $item) {
            $importe = (float) $item->cantidad * (float) $item->p_unit;
            $grupo = $item->grupo?->value ?? (string) $item->grupo;
            $subtotales[$grupo] = ($subtotales[$grupo] ?? 0.0) + $importe;
            $total += $importe;
        }

        return [
            'subtotales' => $subtotales,
            'total' => $total,
        ];
    }
}
