<?php

namespace App\Enums\Qal;

/**
 * Cómo se detectó un cordón. Filete: hay rincón entre las dos piezas. Costura:
 * las dos acaban en la misma arista con las caras coplanares, y va un solo
 * listón por el lado accesible. En la captura, la costura es una junta de
 * ranura.
 */
enum TipoCordon: string
{
    case Filete = 'filete';
    case Costura = 'costura';

    public function tipoDeJunta(): TipoJunta
    {
        return $this === self::Filete ? TipoJunta::Filete : TipoJunta::Ranura;
    }
}
