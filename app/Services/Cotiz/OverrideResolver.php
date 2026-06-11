<?php

namespace App\Services\Cotiz;

use App\Models\Cotiz\Factor;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\ObraFactorOverride;
use App\Models\Cotiz\ObraInsumoOverride;

/**
 * Resuelve los valores efectivos del catálogo (insumo/factor) aplicando la cadena de
 * prioridad de overrides. Port de las cadenas `COALESCE` de `tarjetaTotales.ts`.
 *
 * Prioridad:
 * - Campos de catálogo (descripción, pesos, unidad, centro de costo): `obra > global`.
 * - P.U. del insumo: `tarjeta > obra > global` (el nivel tarjeta se inyecta en Fase 3).
 * - Fórmula del factor: `tarjeta > obra > global`.
 *
 * El nivel "tarjeta" se pasa como parámetro opcional para que el motor de tarjetas
 * (Fase 3) lo enchufe sin reescribir esta resolución.
 */
class OverrideResolver
{
    /**
     * Valores efectivos de un insumo para una obra (sin nivel tarjeta).
     *
     * @return array{
     *     descripcion: string,
     *     codigo_stumis: string|null,
     *     unidad_id: int|null,
     *     precio_unitario: float,
     *     peso_lineal: float|null,
     *     peso_default: float|null,
     *     centro_costo_id: int|null
     * }
     */
    public function resolverInsumo(Insumo $insumo, ?ObraInsumoOverride $override): array
    {
        return [
            'descripcion' => $this->coalesceString($override?->descripcion, $insumo->descripcion) ?? '',
            'codigo_stumis' => $this->coalesceString($override?->codigo_stumis, $insumo->codigo_stumis),
            'unidad_id' => $override?->unidad_id ?? $insumo->unidad_id,
            'precio_unitario' => $this->precioInsumo($insumo, $override),
            'peso_lineal' => $this->coalesceFloat($override?->peso_lineal, $insumo->peso_lineal),
            'peso_default' => $this->coalesceFloat($override?->peso_default, $insumo->peso_default),
            'centro_costo_id' => $override?->centro_costo_id ?? $insumo->centro_costo_id,
        ];
    }

    /**
     * P.U. efectivo de un insumo con la cadena `tarjeta > obra > global`.
     */
    public function precioInsumo(
        Insumo $insumo,
        ?ObraInsumoOverride $override,
        ?float $precioTarjeta = null,
    ): float {
        return $precioTarjeta
            ?? $this->coalesceFloat($override?->precio_unitario, $insumo->precio_unitario)
            ?? 0.0;
    }

    /**
     * Valores efectivos de un factor para una obra.
     *
     * @return array{
     *     nombre: string,
     *     insumo_id: int|null,
     *     formula: string|null,
     *     descripcion: string|null
     * }
     */
    public function resolverFactor(
        Factor $factor,
        ?ObraFactorOverride $override,
        ?string $formulaTarjeta = null,
    ): array {
        return [
            'nombre' => $this->coalesceString($override?->nombre, $factor->nombre) ?? '',
            'insumo_id' => $override?->insumo_id ?? $factor->insumo_id,
            'formula' => $formulaTarjeta
                ?? $this->coalesceString($override?->formula, $factor->formula),
            'descripcion' => $this->coalesceString($override?->descripcion, $factor->descripcion),
        ];
    }

    private function coalesceString(?string $override, ?string $global): ?string
    {
        return ($override !== null && $override !== '') ? $override : $global;
    }

    private function coalesceFloat(int|float|string|null $override, int|float|string|null $global): ?float
    {
        if ($override !== null && $override !== '') {
            return (float) $override;
        }

        return $global !== null ? (float) $global : null;
    }
}
