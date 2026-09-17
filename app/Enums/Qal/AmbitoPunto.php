<?php

namespace App\Enums\Qal;

/**
 * Sobre qué se contesta un punto de inspección: la pieza entera o una junta
 * soldada del mapeo.
 */
enum AmbitoPunto: string
{
    case Pieza = 'pieza';
    case Junta = 'junta';
}
