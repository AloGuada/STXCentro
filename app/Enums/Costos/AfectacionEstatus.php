<?php

namespace App\Enums\Costos;

use App\Enums\Contracts\HasStateTransitions;

enum AfectacionEstatus: string implements HasStateTransitions
{
    case Borrador = 'borrador';
    case Aprobada = 'aprobada';
    case Cancelada = 'cancelada';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Borrador => [self::Aprobada, self::Cancelada],
            self::Aprobada,
            self::Cancelada => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Aprobada => 'Aprobada',
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
