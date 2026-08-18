<?php

namespace App\Enums\Alm;

/**
 * Cuánto pesa el artículo en el inventario, y por lo tanto cada cuánto se
 * cuenta. La idea del inventario cíclico es no volver a parar el almacén un fin
 * de semana entero: se cuenta un pedazo cada semana, y lo caro se repasa más
 * seguido que lo barato.
 */
enum ClasificacionAbc: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';

    public function etiqueta(): string
    {
        return match ($this) {
            self::A => 'Mensual',
            self::B => 'Trimestral',
            self::C => 'Semestral',
        };
    }

    /**
     * Cada cuánto toca contarlo. Son los días de un ABC clásico; el programa
     * cíclico los usa para saber qué hoja generar y cuándo.
     */
    public function frecuenciaDias(): int
    {
        return match ($this) {
            self::A => 30,
            self::B => 90,
            self::C => 180,
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::A => 'Lo caro o de alta rotación. Un faltante aquí se nota en el costo de la obra.',
            self::B => 'Movimiento y valor medios. Se repasa cada tres meses.',
            self::C => 'Lo barato o de poco movimiento. Contarlo seguido cuesta más de lo que vale.',
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
