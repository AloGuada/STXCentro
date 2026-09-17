<?php

namespace App\Enums\Qal;

/**
 * Nivel de inspección del muestreo AQL: cuánto se mira del lote.
 */
enum NivelAql: string
{
    case Reducida = 'I';
    case Normal = 'II';
    case Severa = 'III';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Reducida => 'I — reducida',
            self::Normal => 'II — normal',
            self::Severa => 'III — severa',
        };
    }
}
