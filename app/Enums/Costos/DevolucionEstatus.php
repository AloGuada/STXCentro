<?php

namespace App\Enums\Costos;

use App\Enums\Contracts\HasStateTransitions;

enum DevolucionEstatus: string implements HasStateTransitions
{
    case Vigente = 'vigente';
    case Cancelada = 'cancelada';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Vigente => [self::Cancelada],
            self::Cancelada => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Vigente => 'Vigente',
            self::Cancelada => 'Cancelada',
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
