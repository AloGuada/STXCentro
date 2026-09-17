<?php

namespace App\Enums\Qal;

/**
 * Cómo se contesta un punto de inspección.
 *
 * `seleccion` es la mayoría: el inspector elige una respuesta fija y cada
 * respuesta sabe si es cumplimiento, defecto o no aplica. `contador` es un
 * entero que se captura con los botones de más y menos; `numero`, una medida.
 */
enum TipoDatoPunto: string
{
    case Seleccion = 'seleccion';
    case Numero = 'numero';
    case Contador = 'contador';
    case Texto = 'texto';
}
