<?php

namespace App\Enums\Cotiz;

/**
 * Tipo de pintura de un registro de tarjeta. `Auto` es un meta-valor: no es una
 * fila de `cotiz_pintura_formulas`, infiere la clave real por la descripción del insumo.
 */
enum TipoPintura: string
{
    case Auto = 'auto';
    case NoPinta = 'no_pinta';
    case Placa = 'placa';
    case Tira = 'tira';
    case Hss = 'hss';
    case Ipr = 'ipr';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Automático (por descripción)',
            self::NoPinta => 'No pinta',
            self::Placa => 'Placa (×2)',
            self::Tira => 'Tira / 4PLS (×1)',
            self::Hss => 'HSS / APS',
            self::Ipr => 'IPR / W',
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
