<?php

namespace App\Enums\Cob;

/**
 * Método con el que el IMSS estima la mano de obra de una obra registrada en
 * SIROC. Superficie es el del artículo 18 del reglamento (m² por el costo del
 * DOF); porcentaje aplica un factor de mano de obra al valor del contrato.
 */
enum IcsoeMetodo: string
{
    case Superficie = 'superficie';
    case Porcentaje = 'porcentaje';

    public function label(): string
    {
        return match ($this) {
            self::Superficie => 'Superficie (Art. 18)',
            self::Porcentaje => 'Porcentaje del contrato',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
