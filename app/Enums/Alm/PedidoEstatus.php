<?php

namespace App\Enums\Alm;

/**
 * En qué va un pedido.
 *
 * No hay `parcial`: el avance se lee renglón por renglón, y un estatus más
 * obligaría a mantener sincronizadas dos verdades sobre lo mismo. Un pedido con
 * material pendiente sigue `aprobado`, y por eso sigue apareciendo entre los
 * surtibles.
 *
 * `pendiente` existe pero todavía no se usa: mientras la matriz de aprobadores
 * no esté construida, el pedido nace `aprobado`. Cuando llegue, sólo cambia el
 * default.
 */
enum PedidoEstatus: string
{
    case Borrador = 'borrador';
    case Pendiente = 'pendiente';
    case Aprobado = 'aprobado';
    case Surtido = 'surtido';
    case Cancelado = 'cancelado';
    case Rechazado = 'rechazado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Pendiente => 'Pendiente de firma',
            self::Aprobado => 'Aprobado',
            self::Surtido => 'Surtido',
            self::Cancelado => 'Cancelado',
            self::Rechazado => 'Rechazado',
        };
    }

    /** Si el almacén todavía le debe material. */
    public function admiteSurtido(): bool
    {
        return $this === self::Aprobado;
    }

    /** Si ya no se puede tocar: lo que se surtió, se surtió. */
    public function estaCerrado(): bool
    {
        return in_array($this, [self::Surtido, self::Cancelado, self::Rechazado], true);
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
