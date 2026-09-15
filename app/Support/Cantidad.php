<?php

namespace App\Support;

/**
 * Cómo se imprime una cantidad o un precio unitario que puede traer hasta 4
 * decimales: con 2 si con 2 alcanza (10.00, 45.50) y con los 4 sólo cuando
 * los trae (1.2345). Así el formato de siempre no cambia y lo fino se ve.
 */
final class Cantidad
{
    public static function formatear(float|int|string|null $valor): string
    {
        $numero = (float) $valor;

        if (abs(round($numero, 2) - round($numero, 4)) < 0.00005) {
            return number_format($numero, 2);
        }

        return number_format($numero, 4);
    }
}
