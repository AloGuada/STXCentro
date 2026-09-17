<?php

namespace App\Enums\Qal;

/**
 * Lo que significa la respuesta de un punto, sin importar cómo esté escrita.
 *
 * «Fuera de tol.», «Con defecto», «Incorrecta» y «Falta» son el mismo hecho
 * para el tablero: el punto no cumple. Guardar el resultado aparte del texto
 * es lo que permite contar sin mantener una lista de sinónimos.
 */
enum ResultadoPunto: string
{
    case Ok = 'ok';
    case NoOk = 'no_ok';
    case NoAplica = 'no_aplica';
}
