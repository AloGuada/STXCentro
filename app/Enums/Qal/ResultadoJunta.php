<?php

namespace App\Enums\Qal;

/**
 * El veredicto de una junta del mapeo. No se teclea: con que un punto salga
 * con defecto, la junta entera queda con defecto.
 */
enum ResultadoJunta: string
{
    case Correcta = 'correcta';
    case ConDefecto = 'con_defecto';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Correcta => 'Junta correcta',
            self::ConDefecto => 'Con defecto',
        };
    }
}
