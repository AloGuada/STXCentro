<?php

namespace App\Enums\Qal;

/**
 * Dónde se originó la incidencia que apareció en obra.
 *
 * Es enum y no catálogo editable: son las categorías con las que se llevan las
 * estadísticas históricas —vienen del Excel «ESTADISTICAS INCIDENCIAS EN
 * OBRA»—, y cambiarlas rompe la comparación entre ejercicios.
 *
 * La distinción manda sobre la acción, y por eso el reporte semanal publica los
 * porcentajes por separado en vez de sumarlos: un defecto que se escapó del
 * taller se corrige en planta, y uno aparecido en sitio se repara en obra, que
 * sale más caro.
 */
enum AreaIncidencia: string
{
    case Taller = 'taller';
    case TallerPintura = 'taller_pintura';
    case Montaje = 'montaje';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Taller => 'Taller',
            self::TallerPintura => 'Taller de pintura',
            self::Montaje => 'Montaje',
        };
    }

    /**
     * Si el origen está en planta.
     *
     * El reporte semanal parte la hoja de montaje en dos columnas —taller y
     * montaje— y el taller de pintura cuenta como taller: también es un defecto
     * que salió de la nave.
     */
    public function esDeTaller(): bool
    {
        return $this !== self::Montaje;
    }

    /**
     * @return list<array{valor: string, etiqueta: string}>
     */
    public static function opciones(): array
    {
        return array_map(
            fn (self $area): array => ['valor' => $area->value, 'etiqueta' => $area->etiqueta()],
            self::cases(),
        );
    }
}
