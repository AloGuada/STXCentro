<?php

namespace App\Enums\Cob;

/**
 * Estado de un seguimiento ICSOE. `pendiente_verificacion` es el que alimenta
 * el badge del sidebar: significa que el valor a ejecutar del proyecto cambió
 * y alguien debe revisar el recálculo antes de reportar al IMSS.
 */
enum IcsoeEstatus: string
{
    case Vigente = 'vigente';
    case PendienteVerificacion = 'pendiente_verificacion';
    case Cerrado = 'cerrado';

    public function label(): string
    {
        return match ($this) {
            self::Vigente => 'Vigente',
            self::PendienteVerificacion => 'Pendiente de verificación',
            self::Cerrado => 'Cerrado',
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

    /**
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
