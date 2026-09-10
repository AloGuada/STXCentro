<?php

namespace App\Enums\Qal;

/**
 * Perfil o placa en la 1ª transformación. Se inspeccionan distinto: la placa
 * lleva el formato F-STX-CA-03.
 */
enum SubtipoPrimera: string
{
    case Perfil = 'perfil';
    case Placa = 'placa';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Perfil => 'Perfil',
            self::Placa => 'Placa',
        };
    }
}
