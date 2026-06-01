<?php

namespace App\Enums\Costos;

use App\Enums\Contracts\HasStateTransitions;

enum RubroAfectadoEstatus: string implements HasStateTransitions
{
    case Apartado = 'apartado';
    case Aplicado = 'aplicado';
    case Vencido = 'vencido';
    case Cancelado = 'cancelado';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Apartado => [self::Aplicado, self::Cancelado, self::Vencido],
            self::Aplicado => [self::Cancelado],
            self::Vencido,
            self::Cancelado => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Apartado => 'Apartado',
            self::Aplicado => 'Aplicado',
            self::Vencido => 'Vencido',
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

    /**
     * Estados que cuentan vivos contra el acumulado del rubro.
     *
     * @return array<int, self>
     */
    public static function activos(): array
    {
        return [self::Apartado, self::Aplicado];
    }
}
