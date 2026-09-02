<?php

namespace App\Enums\Alm;

/**
 * Qué clase de lugar es dentro del almacén. No hay jerarquía obligada entre
 * ellos —un contenedor de obra no tiene racks ni niveles—: el tipo sólo nombra
 * el lugar, y quién cuelga de quién lo dice `padre_id`.
 */
enum UbicacionTipo: string
{
    case Pasillo = 'pasillo';
    case Rack = 'rack';
    case Nivel = 'nivel';
    case Contenedor = 'contenedor';
    case Zona = 'zona';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pasillo => 'Pasillo',
            self::Rack => 'Rack',
            self::Nivel => 'Nivel',
            self::Contenedor => 'Contenedor',
            self::Zona => 'Zona',
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
