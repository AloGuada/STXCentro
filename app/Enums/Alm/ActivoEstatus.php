<?php

namespace App\Enums\Alm;

/**
 * En qué anda una pieza.
 *
 * `prestado` y `en_reparacion` siguen siendo del almacén: pesan en la
 * existencia aunque no se puedan entregar hoy. `baja` es la única que deja de
 * contar — la pieza ya no existe para el almacén, aunque su historia se
 * conserve.
 */
enum ActivoEstatus: string
{
    case Disponible = 'disponible';
    case Prestado = 'prestado';
    case EnReparacion = 'en_reparacion';
    case Baja = 'baja';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Disponible => 'Disponible',
            self::Prestado => 'Prestado',
            self::EnReparacion => 'En reparación',
            self::Baja => 'Baja',
        };
    }

    /** Si la pieza sigue contando en la existencia del almacén. */
    public function cuentaEnExistencia(): bool
    {
        return $this !== self::Baja;
    }

    /** Si se puede prometer hoy. Prestada y en reparación son de la empresa, pero no están. */
    public function sePuedeEntregar(): bool
    {
        return $this === self::Disponible;
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
