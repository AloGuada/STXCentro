<?php

namespace App\Enums\Costos;

enum TipoFiscalPartida: string
{
    case Mercancia = 'mercancia';
    case Flete = 'flete';
    case ServicioProfesional = 'servicio_profesional';
    case Renta = 'renta';

    public function label(): string
    {
        return match ($this) {
            self::Mercancia => 'Mercancía',
            self::Flete => 'Flete',
            self::ServicioProfesional => 'Servicio profesional',
            self::Renta => 'Renta',
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
