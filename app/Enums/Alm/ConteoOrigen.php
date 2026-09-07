<?php

namespace App\Enums\Alm;

/**
 * De dónde salió la hoja: del programa que reparte el almacén en días, o de
 * alguien que sospechó un faltante y no quiso esperar a que le tocara.
 */
enum ConteoOrigen: string
{
    case Programado = 'programado';
    case Manual = 'manual';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Programado => 'Programa',
            self::Manual => 'Suelto',
        };
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
