<?php

namespace App\Enums\Costos;

use App\Enums\Contracts\HasStateTransitions;

enum RequisicionEstatus: string implements HasStateTransitions
{
    case Borrador = 'borrador';
    case Cotizada = 'cotizada';
    case PendienteAprobacion = 'pendiente_aprobacion';
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
            self::Borrador => [self::Cotizada, self::Cancelada],
            self::Cotizada => [self::Borrador, self::PendienteAprobacion, self::Cancelada],
            self::PendienteAprobacion => [self::Aprobada, self::Rechazada, self::Cancelada],
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
            self::Cotizada => 'Cotizada',
            self::PendienteAprobacion => 'Pendiente de aprobación',
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
