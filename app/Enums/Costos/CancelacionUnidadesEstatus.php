<?php

namespace App\Enums\Costos;

use App\Enums\Contracts\HasStateTransitions;

/**
 * Vida de una cancelación de unidades de la orden de compra. Nace pendiente y
 * sólo surte efecto cuando el jefe de compras la autoriza; rechazarla no toca
 * cantidades ni presupuesto. Los dos terminales no regresan.
 */
enum CancelacionUnidadesEstatus: string implements HasStateTransitions
{
    case Pendiente = 'pendiente';
    case Autorizada = 'autorizada';
    case Rechazada = 'rechazada';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pendiente => [self::Autorizada, self::Rechazada],
            self::Autorizada, self::Rechazada => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente de autorizar',
            self::Autorizada => 'Autorizada',
            self::Rechazada => 'Rechazada',
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
