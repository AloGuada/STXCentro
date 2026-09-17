<?php

namespace App\Enums\Qal;

/**
 * El veredicto del laboratorio sobre un punto examinado.
 *
 * Son dos y no tres: el laboratorio dicta si el punto cumple la norma o no.
 * «Reparado» o «pendiente» no van aquí —eso es lo que hace Calidad después—, y
 * el reexamen posterior a una reparación entra como su propio renglón del
 * informe, no corrigiendo el veredicto del anterior. Si se sobrescribiera, el
 * porcentaje de rechazo de la obra bajaría al reparar, que es justo lo que no
 * debe pasar: lo reparado se reparó porque fue rechazado.
 */
enum ResultadoPnd: string
{
    case Aceptada = 'aceptada';
    case Rechazada = 'rechazada';

    public function nombre(): string
    {
        return match ($this) {
            self::Aceptada => 'Aceptada',
            self::Rechazada => 'Rechazada',
        };
    }
}
