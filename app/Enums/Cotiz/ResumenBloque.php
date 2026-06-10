<?php

namespace App\Enums\Cotiz;

/**
 * Bloque visual al que pertenece una fila del Resumen de proyecto.
 */
enum ResumenBloque: string
{
    case MoFab = 'MO_FAB';
    case MoMontaje = 'MO_MONTAJE';
    case Extras = 'EXTRAS';
    case Totales = 'TOTALES';

    public function label(): string
    {
        return match ($this) {
            self::MoFab => 'Mano de obra fabricación',
            self::MoMontaje => 'Mano de obra montaje',
            self::Extras => 'Extras',
            self::Totales => 'Totales',
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
