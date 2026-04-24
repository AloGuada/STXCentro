<?php

namespace App\Enums\Costos;

enum SolicitudPagoEstatus: string
{
    case Borrador = 'borrador';
    case PendienteFirma = 'pendiente_firma';
    case Aprobada = 'aprobada';
    case Pagada = 'pagada';
    case Cancelada = 'cancelada';

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
