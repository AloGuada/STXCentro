<?php

namespace App\Enums\Prod;

enum EstadoAsistencia: string
{
    case Asistencia = 'asistencia';
    case Falta = 'falta';
    case Vacaciones = 'vacaciones';
    case NoAplica = 'no_aplica';

    public function label(): string
    {
        return match ($this) {
            self::Asistencia => 'Asistencia',
            self::Falta => 'Falta',
            self::Vacaciones => 'Vacaciones',
            self::NoAplica => 'No aplica',
        };
    }

    /**
     * Días que cuentan como pagados para el sueldo base: se asiste o se está
     * de vacaciones (que por ley se pagan). Falta y "no aplica" no cuentan.
     */
    public function cuentaComoPagado(): bool
    {
        return match ($this) {
            self::Asistencia, self::Vacaciones => true,
            self::Falta, self::NoAplica => false,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
