<?php

namespace App\Enums\Costos;

use App\Enums\Contracts\HasStateTransitions;

enum RubroAfectadoEstatus: string implements HasStateTransitions
{
    case Aplicado = 'aplicado';
    case Cancelado = 'cancelado';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Aplicado => [self::Cancelado],
            self::Cancelado => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Aplicado => 'Aplicado',
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
