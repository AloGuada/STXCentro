<?php

namespace App\Enums\Costos;

use App\Enums\Contracts\HasStateTransitions;

enum PresupuestoEstatus: string implements HasStateTransitions
{
    case Activo = 'activo';
    case Cerrado = 'cerrado';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Activo => [self::Cerrado],
            self::Cerrado => [self::Activo],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Cerrado => 'Cerrado',
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
