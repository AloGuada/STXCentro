<?php

namespace App\Enums\Qal;

/**
 * A quién se le atribuye la incidencia.
 *
 * Los mismos siete departamentos del Excel, sin añadir ni quitar: la
 * estadística por responsable es el uso principal del módulo —dice a qué área
 * mandar la acción correctiva— y sólo sirve si se puede comparar con los años
 * anteriores.
 *
 * `1A` y `2A` son la primera y la segunda transformación. Se guardan con clave
 * de texto y no con el número, porque `1`/`2` no dicen nada al leer la base.
 */
enum DepartamentoIncidencia: string
{
    case PrimeraTransformacion = '1a';
    case SegundaTransformacion = '2a';
    case PinturaTaller = 'pintura_taller';
    case PinturaObra = 'pintura_obra';
    case Ingenieria = 'ingenieria';
    case Logistica = 'logistica';
    case Construccion = 'construccion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PrimeraTransformacion => '1A',
            self::SegundaTransformacion => '2A',
            self::PinturaTaller => 'PINTURA TALLER',
            self::PinturaObra => 'PINTURA OBRA',
            self::Ingenieria => 'INGENIERIA',
            self::Logistica => 'LOGISTICA',
            self::Construccion => 'CONSTRUCCION',
        };
    }

    /**
     * Si el responsable es un departamento de pintura.
     *
     * El reporte semanal dedica una hoja entera al recubrimiento, y su corte
     * sale de aquí: son las dos únicas que entran, partidas en taller y obra.
     */
    public function esDePintura(): bool
    {
        return $this === self::PinturaTaller || $this === self::PinturaObra;
    }

    /**
     * @return list<array{valor: string, etiqueta: string}>
     */
    public static function opciones(): array
    {
        return array_map(
            fn (self $departamento): array => [
                'valor' => $departamento->value,
                'etiqueta' => $departamento->etiqueta(),
            ],
            self::cases(),
        );
    }
}
