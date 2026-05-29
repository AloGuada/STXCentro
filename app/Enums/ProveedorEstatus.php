<?php

namespace App\Enums;

enum ProveedorEstatus: string
{
    case PendienteValidacion = 'pendiente_validacion';
    case Activo = 'activo';
    case Rechazado = 'rechazado';

    public function label(): string
    {
        return match ($this) {
            self::PendienteValidacion => 'Pendiente de validación',
            self::Activo => 'Activo',
            self::Rechazado => 'Rechazado',
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
