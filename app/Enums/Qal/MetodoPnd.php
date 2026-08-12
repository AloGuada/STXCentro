<?php

namespace App\Enums\Qal;

/**
 * Métodos de prueba no destructiva.
 *
 * Es enum y no catálogo: los define la norma, no la empresa. Nadie debe poder
 * inventar un método desde una pantalla de administración.
 */
enum MetodoPnd: string
{
    case Ut = 'UT';
    case Mt = 'MT';
    case Pt = 'PT';
    case Rt = 'RT';
    case Vt = 'VT';

    public function nombre(): string
    {
        return match ($this) {
            self::Ut => 'Ultrasonido',
            self::Mt => 'Partículas magnéticas',
            self::Pt => 'Líquidos penetrantes',
            self::Rt => 'Radiografía',
            self::Vt => 'Visual de laboratorio',
        };
    }

    /** Qué detecta cada uno. Es lo que decide cuál se pacta en el contrato. */
    public function detecta(): string
    {
        return match ($this) {
            self::Ut => 'Discontinuidades internas',
            self::Mt => 'Superficiales y sub-superficiales',
            self::Pt => 'Abiertas a la superficie',
            self::Rt => 'Interna, con placa',
            self::Vt => 'Inspección visual certificada',
        };
    }
}
