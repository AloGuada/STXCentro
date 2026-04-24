<?php

namespace App\Enums\Costos;

use App\Enums\Contracts\HasStateTransitions;

enum FacturaEstatus: string implements HasStateTransitions
{
    case PendienteEntrega = 'pendiente_entrega';
    case PendienteAprobacion = 'pendiente_aprobacion';
    case PendientePago = 'pendiente_pago';
    case Pagada = 'pagada';
    case Cancelada = 'cancelada';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendienteEntrega => [self::PendienteAprobacion, self::Cancelada],
            self::PendienteAprobacion => [self::PendientePago, self::Cancelada],
            self::PendientePago => [self::Pagada, self::Cancelada],
            self::Pagada,
            self::Cancelada => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PendienteEntrega => 'Pendiente de entrega',
            self::PendienteAprobacion => 'Pendiente de aprobación',
            self::PendientePago => 'Pendiente de pago',
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
