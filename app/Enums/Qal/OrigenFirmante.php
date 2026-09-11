<?php

namespace App\Enums\Qal;

/**
 * De dónde sale quien firma en un lugar de la hoja.
 *
 * «Creador» cambia con cada documento: es quien lo elaboró —el inspector del
 * reporte—. «Usuario» es siempre la misma persona —la jefatura de calidad—, y
 * si todavía no se eligió la raya sale en blanco para firmarse a mano.
 */
enum OrigenFirmante: string
{
    case Creador = 'creador';
    case Usuario = 'usuario';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Creador => 'Quien elaboró el documento',
            self::Usuario => 'Una persona fija',
        };
    }

    /**
     * @return list<array{valor: string, etiqueta: string}>
     */
    public static function opciones(): array
    {
        return array_map(
            fn (self $origen): array => ['valor' => $origen->value, 'etiqueta' => $origen->etiqueta()],
            self::cases(),
        );
    }
}
