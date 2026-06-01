<?php

namespace App\Enums\Costos;

use App\Enums\Contracts\HasStateTransitions;

enum SolicitudPagoEstatus: string implements HasStateTransitions
{
    case Borrador = 'borrador';
    case PendienteFirma = 'pendiente_firma';
    case Aprobada = 'aprobada';
    case Pagada = 'pagada';
    case Cancelada = 'cancelada';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Borrador => [self::PendienteFirma, self::Cancelada],
            self::PendienteFirma => [self::Aprobada, self::Cancelada],
            self::Aprobada => [self::Pagada, self::Cancelada],
            self::Pagada,
            self::Cancelada => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::PendienteFirma => 'Pendiente de firma',
            self::Aprobada => 'Aprobada',
            self::Pagada => 'Pagada',
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
