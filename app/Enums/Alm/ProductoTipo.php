<?php

namespace App\Enums\Alm;

/**
 * Qué clase de artículo es, para el catálogo que comparten Compras y Almacén.
 * Decide si el material se gasta o si sale y regresa: el insumo se consume y el
 * activo es un bien de la empresa que se presta bajo resguardo y regresa.
 *
 * La herramienta no es un tercer caso: se comporta igual que el activo, y
 * separarlas sólo obligaba a decidir en el alta de qué lado cae una pulidora.
 */
enum ProductoTipo: string
{
    case Insumo = 'insumo';
    case Activo = 'activo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Insumo => 'Insumo',
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
