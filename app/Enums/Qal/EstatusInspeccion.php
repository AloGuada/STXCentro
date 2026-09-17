<?php

namespace App\Enums\Qal;

/**
 * Cómo quedó la pieza en esa inspección.
 *
 * En armado y vestido «pendiente» es el veredicto correcto: la pieza no es
 * producto terminado y no puede quedar liberada todavía.
 */
enum EstatusInspeccion: string
{
    case Pendiente = 'pendiente';
    case Liberado = 'liberado';
    case Rechazado = 'rechazado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Liberado => 'Liberado',
            self::Rechazado => 'Rechazado',
        };
    }
}
