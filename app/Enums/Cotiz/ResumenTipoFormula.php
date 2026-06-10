<?php

namespace App\Enums\Cotiz;

/**
 * Tipo de fórmula que determina cómo se computa el valor de una fila del Resumen.
 */
enum ResumenTipoFormula: string
{
    case Materiales = 'materiales';
    case PorKg = 'por_kg';
    case PorM2Pintura = 'por_m2_pintura';
    case MoFabSubgrupo = 'mo_fab_subgrupo';
    case FleteKgProrrateado = 'flete_kg_prorrateado';
    case ViaticoM2Prorrateado = 'viatico_m2_prorrateado';
    case Subtotal = 'subtotal';
    case Margen = 'margen';
    case Total = 'total';

    public function label(): string
    {
        return match ($this) {
            self::Materiales => 'Materiales',
            self::PorKg => 'Por kilo',
            self::PorM2Pintura => 'Por m² de pintura',
            self::MoFabSubgrupo => 'MO fabricación (subgrupo)',
            self::FleteKgProrrateado => 'Flete prorrateado por kg',
            self::ViaticoM2Prorrateado => 'Viático prorrateado por m²',
            self::Subtotal => 'Subtotal',
            self::Margen => 'Margen',
            self::Total => 'Total',
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
