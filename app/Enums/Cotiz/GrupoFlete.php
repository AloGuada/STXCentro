<?php

namespace App\Enums\Cotiz;

/**
 * Grupo del catálogo de fletes y viáticos (9 grupos del Excel original).
 */
enum GrupoFlete: string
{
    case Viaticos = 'VIATICOS';
    case SupervMontaje = 'SUPERV_MONTAJE';
    case Energia = 'ENERGIA';
    case Varios = 'VARIOS';
    case Fletes = 'FLETES';
    case Gruas = 'GRUAS';
    case Plataformas = 'PLATAFORMAS';
    case Laboratorio = 'LABORATORIO';
    case Topografia = 'TOPOGRAFIA';

    public function label(): string
    {
        return match ($this) {
            self::Viaticos => 'Viáticos',
            self::SupervMontaje => 'Supervisión de montaje',
            self::Energia => 'Energía',
            self::Varios => 'Varios',
            self::Fletes => 'Fletes',
            self::Gruas => 'Grúas',
            self::Plataformas => 'Plataformas',
            self::Laboratorio => 'Laboratorio',
            self::Topografia => 'Topografía',
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
}
