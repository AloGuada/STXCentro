<?php

namespace App\Enums\Qal;

/**
 * En qué va el dosier de una obra: se arma (borrador), lo revisa la jefatura
 * (en revisión) y se entrega al cliente, con fecha.
 */
enum EstatusDossier: string
{
    case Borrador = 'borrador';
    case EnRevision = 'en_revision';
    case Entregado = 'entregado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::EnRevision => 'En revisión',
            self::Entregado => 'Entregado',
        };
    }

    /**
     * @return list<array{valor: string, etiqueta: string}>
     */
    public static function opciones(): array
    {
        return array_map(
            fn (self $estatus): array => ['valor' => $estatus->value, 'etiqueta' => $estatus->etiqueta()],
            self::cases(),
        );
    }
}
