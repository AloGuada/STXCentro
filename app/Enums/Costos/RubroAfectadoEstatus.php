<?php

namespace App\Enums\Costos;

enum RubroAfectadoEstatus: string
{
    case Aplicado = 'aplicado';
    case Cancelado = 'cancelado';

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
