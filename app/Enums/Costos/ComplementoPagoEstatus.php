<?php

namespace App\Enums\Costos;

use App\Enums\Contracts\HasStateTransitions;

enum ComplementoPagoEstatus: string implements HasStateTransitions
{
    case Pendiente = 'pendiente';
    case Cumplido = 'cumplido';
    case Vencido = 'vencido';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pendiente => [self::Cumplido, self::Vencido],
            self::Vencido => [self::Cumplido],
            self::Cumplido => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Cumplido => 'Cumplido',
            self::Vencido => 'Vencido',
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
