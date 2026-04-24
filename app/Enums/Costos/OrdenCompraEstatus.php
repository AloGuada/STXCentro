<?php

namespace App\Enums\Costos;

use App\Enums\Contracts\HasStateTransitions;

enum OrdenCompraEstatus: string implements HasStateTransitions
{
    case PendienteFactura = 'pendiente_factura';
    case PendienteEntrega = 'pendiente_entrega';
    case PendienteAprobacion = 'pendiente_aprobacion';
    case PendientePago = 'pendiente_pago';
    case Pagada = 'pagada';
    case Cancelada = 'cancelada';

    /**
     * Transiciones permitidas desde este estado por acción directa del usuario.
     * Los recálculos automáticos (recalcularEstatus) siguen vía ->update() directo.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendienteFactura,
            self::PendienteEntrega,
            self::PendienteAprobacion => [self::Cancelada],
            self::PendientePago,
            self::Pagada,
            self::Cancelada => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PendienteFactura => 'Pendiente de factura',
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
