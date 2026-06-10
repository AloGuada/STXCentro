<?php

namespace App\Enums\Cotiz;

/**
 * Tipo de corte de una categoría de kilos: decide a qué variable de factor
 * se suman los kg en el Análisis de kilos reales.
 */
enum TipoCorte: string
{
    case Tiras = 'TIRAS';
    case Raz = 'RAZ';
    case Kg = 'KG';
    case Cnx = 'CNX';

    public function label(): string
    {
        return match ($this) {
            self::Tiras => 'Tiras',
            self::Raz => 'RAZ-Robot',
            self::Kg => 'Kilos',
            self::Cnx => 'Conexiones (CNX)',
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
