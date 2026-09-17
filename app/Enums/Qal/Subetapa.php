<?php

namespace App\Enums\Qal;

/**
 * En qué momento de la 2ª transformación se inspecciona.
 *
 * En armado y vestido se revisa la preparación de las juntas, antes de soldar;
 * en soldado, el producto terminado. Saber cuándo se inspeccionó es lo que
 * permite decir dónde nacen los errores.
 */
enum Subetapa: string
{
    case ArmadoVestido = 'armado_vestido';
    case Soldado = 'soldado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ArmadoVestido => 'Armado / Vestido',
            self::Soldado => 'Soldado',
        };
    }
}
