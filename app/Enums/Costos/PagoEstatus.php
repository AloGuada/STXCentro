<?php

namespace App\Enums\Costos;

enum PagoEstatus: string
{
    case Pendiente = 'pendiente';
    case Programado = 'programado';
    case Parcial = 'parcial';
    case Pagado = 'pagado';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Programado => 'Programado',
            self::Parcial => 'Parcial',
            self::Pagado => 'Pagado',
            self::Cancelado => 'Cancelado',
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
