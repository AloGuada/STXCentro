<?php

namespace App\Enums\Qal;

/**
 * Filete o ranura. El filete se mide contra el tamaño del plano; la ranura no
 * lleva medida y no revisa la preparación de filete.
 */
enum TipoJunta: string
{
    case Filete = 'filete';
    case Ranura = 'ranura';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Filete => 'Filete',
            self::Ranura => 'Ranura',
        };
    }
}
