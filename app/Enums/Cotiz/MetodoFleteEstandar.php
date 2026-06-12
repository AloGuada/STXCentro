<?php

namespace App\Enums\Cotiz;

/**
 * Método de cálculo de camiones en el análisis de fletes estándar (EXPL. M.O rows 89-117).
 *
 * - PorKg: el volumen está en kg → camiones = ROUNDUP(vol / kg_por_camion, 2).
 * - PorPiezas: el volumen está en ml → pzas = CEIL(vol / ml_por_pza) → camiones = ROUNDUP(pzas / pzas_por_camion, 2).
 */
enum MetodoFleteEstandar: string
{
    case PorKg = 'por_kg';
    case PorPiezas = 'por_piezas';

    public function label(): string
    {
        return match ($this) {
            self::PorKg => 'Por kg',
            self::PorPiezas => 'Por piezas',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
