<?php

namespace App\Enums\Qal;

/**
 * Las tres transformaciones por las que pasa una pieza, y que dan su nombre a
 * los tres formularios de captura.
 *
 * Los valores conservan la ordinal con voladita (`1ª`) porque es como están
 * escritos en el sistema que se migra y en los formatos impresos.
 */
enum FaseTransformacion: string
{
    case Primera = '1ª';
    case Segunda = '2ª';
    case Tercera = '3ª';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Primera => '1ª · Corte y habilitado',
            self::Segunda => '2ª · Armado y soldadura',
            self::Tercera => '3ª · Pintura',
        };
    }
}
