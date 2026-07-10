<?php

namespace App\Enums;

enum TipoProveedor: string
{
    case Proveedor = 'proveedor';
    case Tercero = 'tercero';
    case Servicio = 'servicio';

    public function label(): string
    {
        return match ($this) {
            self::Proveedor => 'Proveedor',
            self::Tercero => 'Tercero',
            self::Servicio => 'Servicio',
        };
    }

    /**
     * Los tipos que capturan cuenta bancaria (Proveedor y Tercero). Servicio se
     * paga por banca en línea (luz/agua) y no tiene cuenta propia.
     */
    public function usaCuentaBancaria(): bool
    {
        return $this !== self::Servicio;
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
