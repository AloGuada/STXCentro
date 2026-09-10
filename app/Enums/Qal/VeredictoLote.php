<?php

namespace App\Enums\Qal;

/**
 * Lo que dice el muestreo del lote entero. Mientras no se mira la muestra
 * completa no hay veredicto: se guarda nulo, no «aceptado».
 */
enum VeredictoLote: string
{
    case Aceptado = 'aceptado';
    case Rechazado = 'rechazado';
}
