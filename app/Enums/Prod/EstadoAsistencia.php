<?php

namespace App\Enums\Prod;

enum EstadoAsistencia: string
{
    case Asistencia = 'asistencia';
    case Falta = 'falta';
    case Vacaciones = 'vacaciones';
    case Incapacidad = 'incapacidad';
    case NoAplica = 'no_aplica';

    public function label(): string
    {
        return match ($this) {
            self::Asistencia => 'Asistencia',
            self::Falta => 'Falta',
            self::Vacaciones => 'Vacaciones',
            self::Incapacidad => 'Incapacidad',
            self::NoAplica => 'No aplica',
        };
    }

    /**
     * Días que aporta al sueldo base garantizado. Se asiste, se está de
     * vacaciones (que por ley se pagan) o incapacitado: los tres cuentan como
     * día trabajado. La falta no cuenta, y "no aplica" además anula la semana.
     */
    public function valorEnDias(): float
    {
        return match ($this) {
            self::Asistencia, self::Vacaciones, self::Incapacidad => 1.0,
            self::Falta, self::NoAplica => 0.0,
        };
    }

    /**
     * "No aplica" marca al trabajador que esa semana no cobra sueldo base:
     * basta un día así para que la semana entera se pague sólo con destajo.
     */
    public function anulaSueldoBase(): bool
    {
        return $this === self::NoAplica;
    }

    /**
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
