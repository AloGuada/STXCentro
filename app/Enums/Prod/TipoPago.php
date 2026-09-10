<?php

namespace App\Enums\Prod;

/**
 * Con qué regla paga un grupo de precios. Las dos modalidades son excluyentes:
 * un mismo grupo no puede cobrar por kilo y por subproceso, porque el renglón
 * liquidado no sabría cuál de los dos importes es el bueno.
 *
 * `Kilo` es la histórica: cada proceso tiene su $/kg y la pieza paga su peso.
 * `Subproceso` desmenuza el proceso en pasos con precio fijo por pieza, para el
 * trabajo donde el peso no explica lo que cuesta hacerlo.
 */
enum TipoPago: string
{
    case Kilo = 'kilo';
    case Subproceso = 'subproceso';

    public function label(): string
    {
        return match ($this) {
            self::Kilo => 'Por kilo',
            self::Subproceso => 'Por subproceso',
        };
    }

    /**
     * Cómo se lee el precio del renglón en las pantallas y en la orden de pago.
     */
    public function unidad(): string
    {
        return match ($this) {
            self::Kilo => '$/kg',
            self::Subproceso => '$/pieza',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function opciones(): array
    {
        return array_map(
            fn (self $caso): array => ['value' => $caso->value, 'label' => $caso->label()],
            self::cases(),
        );
    }
}
