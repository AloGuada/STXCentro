<?php

namespace App\Enums\Alm;

/**
 * Para qué sirve el almacén. No cambia las reglas del kardex; separa el material
 * de consumo del que se monta en obra y del que se presta y regresa.
 */
enum AlmacenTipo: string
{
    case Insumos = 'insumos';
    case Montaje = 'montaje';
    case Herramienta = 'herramienta';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Insumos => 'Insumos',
            self::Montaje => 'Montaje',
            self::Herramienta => 'Herramienta',
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
