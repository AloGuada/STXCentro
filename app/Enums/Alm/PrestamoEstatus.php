<?php

namespace App\Enums\Alm;

/**
 * Un resguardo está abierto mientras le quede algo afuera. Cierra solo, con la
 * devolución del último renglón; nadie lo cierra a mano.
 */
enum PrestamoEstatus: string
{
    case Abierto = 'abierto';
    case Cerrado = 'cerrado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Abierto => 'Afuera',
            self::Cerrado => 'Devuelto',
        };
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
