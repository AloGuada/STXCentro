<?php

namespace App\Enums\Costos;

enum OrdenCompraEstatus: string
{
    case PendienteFactura = 'pendiente_factura';
    case PendienteEntrega = 'pendiente_entrega';
    case PendienteAprobacion = 'pendiente_aprobacion';
    case PendientePago = 'pendiente_pago';
    case Pagada = 'pagada';
    case Cancelada = 'cancelada';

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
