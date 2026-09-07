<?php

namespace App\Enums\Alm;

/**
 * En qué va una hoja de conteo. Nace pendiente, pasa a contando cuando alguien
 * captura el primer renglón, y cierra generando el ajuste. Cancelada es la
 * hoja que nunca se contó y ya no se va a contar: se deja para que el
 * programa diga que ese día no se hizo, en vez de borrar la evidencia.
 */
enum ConteoEstatus: string
{
    case Pendiente = 'pendiente';
    case Contando = 'contando';
    case Cerrado = 'cerrado';
    case Cancelado = 'cancelado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Contando => 'Contando',
            self::Cerrado => 'Cerrado',
            self::Cancelado => 'Cancelado',
        };
    }

    /** Las que todavía esperan a que alguien cuente. */
    public function abierto(): bool
    {
        return in_array($this, [self::Pendiente, self::Contando], true);
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
