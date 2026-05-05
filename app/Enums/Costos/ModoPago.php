<?php

namespace App\Enums\Costos;

enum ModoPago: string
{
    case Contado = 'contado';
    case Credito = 'credito';

    public function label(): string
    {
        return match ($this) {
            self::Contado => 'Contado',
            self::Credito => 'Crédito',
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
