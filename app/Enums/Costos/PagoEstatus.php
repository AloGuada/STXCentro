<?php

namespace App\Enums\Costos;

use App\Enums\Contracts\HasStateTransitions;

enum PagoEstatus: string implements HasStateTransitions
{
    case Pendiente = 'pendiente';
    case Programado = 'programado';
    case Parcial = 'parcial';
    case Pagado = 'pagado';
    case Cancelado = 'cancelado';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pendiente => [self::Programado, self::Cancelado],
            self::Programado => [self::Pagado, self::Parcial, self::Cancelado],
            self::Parcial => [self::Pagado, self::Cancelado],
            self::Pagado,
            self::Cancelado => [],
        };
    }

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
