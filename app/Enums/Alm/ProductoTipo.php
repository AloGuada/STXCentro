<?php

namespace App\Enums\Alm;

/**
 * Qué clase de artículo es, para el catálogo que comparten Compras y Almacén.
 * Decide si el material se gasta o si sale y regresa: el insumo se consume, la
 * herramienta se presta bajo resguardo y el activo es un bien de la empresa.
 */
enum ProductoTipo: string
{
    case Insumo = 'insumo';
    case Herramienta = 'herramienta';
    case Activo = 'activo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Insumo => 'Insumo',
            self::Herramienta => 'Herramienta',
            self::Activo => 'Activo',
        };
    }

    /**
     * Lo que sale y regresa se lleva pieza por pieza: sin identidad individual
     * no hay forma de saber quién tiene cuál ni desde cuándo.
     */
    public function admiteControlPorPieza(): bool
    {
        return $this !== self::Insumo;
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
