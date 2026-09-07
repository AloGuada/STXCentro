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
     * Días que aporta al sueldo base garantizado. Se asiste o se está de
     * vacaciones (que por ley se pagan): los dos cuentan como día trabajado.
     * La incapacidad NO cuenta: ese día lo paga el IMSS, no el destajo
     * (corrección 2026-09-08 contra la nómina real: un trabajador incapacitado
     * toda la semana salía con 7 días pagados). La falta tampoco, y "no
     * aplica" además anula la semana.
     */
    public function valorEnDias(): float
    {
        return match ($this) {
            self::Asistencia, self::Vacaciones => 1.0,
            self::Falta, self::Incapacidad, self::NoAplica => 0.0,
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
     * Un día en que el trabajador no estuvo en la línea. Quien tiene TODA la
     * semana así no aportó nada al destajo y no entra al reparto del
     * excedente, aunque siga en el grupo.
     */
    public function esAusencia(): bool
    {
        return in_array($this, [self::Falta, self::Incapacidad], true);
    }

    /**
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
