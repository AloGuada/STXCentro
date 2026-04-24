<?php

namespace App\Enums\Costos;

/**
 * Desde qué fecha se cuentan los dias_credito para calcular la fecha de pago
 * de una factura.
 */
enum BaseDiasCredito: string
{
    case Factura = 'factura';
    case Recepcion = 'recepcion';
    case Aprobacion = 'aprobacion';

    public function label(): string
    {
        return match ($this) {
            self::Factura => 'Fecha de factura',
            self::Recepcion => 'Fecha de recepción',
            self::Aprobacion => 'Fecha de aprobación',
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
