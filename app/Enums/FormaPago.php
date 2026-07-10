<?php

namespace App\Enums;

enum FormaPago: string
{
    case Transferencia = 'transferencia';
    case ChequeEfectivo = 'cheque_efectivo';

    public function label(): string
    {
        return match ($this) {
            self::Transferencia => 'Transferencia',
            self::ChequeEfectivo => 'Cheque / Efectivo',
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
