<?php

namespace App\Enums\Costos;

use App\Enums\Contracts\HasStateTransitions;

enum AnticipoEstatus: string implements HasStateTransitions
{
    case Vigente = 'vigente';
    case Agotado = 'agotado';
    case Cancelado = 'cancelado';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Vigente => [self::Agotado, self::Cancelado],
            self::Agotado,
            self::Cancelado => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Vigente => 'Vigente',
            self::Agotado => 'Agotado',
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
