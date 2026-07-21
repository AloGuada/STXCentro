<?php

namespace App\Enums\Costos;

use App\Enums\Contracts\HasStateTransitions;

enum RequisicionEstatus: string implements HasStateTransitions
{
    case Borrador = 'borrador';
    case PendienteAprobacion = 'pendiente_aprobacion_interno';
    case Aprobada = 'aprobada';
    case Rechazada = 'rechazada';
    case Liberada = 'liberada';
    case Cancelada = 'cancelada';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Borrador => [self::PendienteAprobacion, self::Cancelada],
            self::PendienteAprobacion => [self::Borrador, self::Aprobada, self::Rechazada, self::Cancelada],
            self::Aprobada => [self::Liberada, self::Cancelada],
            self::Rechazada => [self::Borrador, self::Cancelada],
            self::Liberada,
            self::Cancelada => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::PendienteAprobacion => 'Pendiente de aprobación interna',
            self::Aprobada => 'Aprobada',
            self::Rechazada => 'Rechazada',
            self::Liberada => 'Liberada',
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
