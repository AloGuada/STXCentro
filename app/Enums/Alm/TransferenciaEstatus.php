<?php

namespace App\Enums\Alm;

/**
 * Los dos tiempos de una transferencia.
 *
 * «Recibida con faltante» no es un estatus aparte: es una recepción cerrada en
 * la que lo confirmado no alcanzó lo enviado, y se distingue en pantalla porque
 * es lo que alguien tiene que ir a explicar. Meterlo aquí obligaría a mantener
 * sincronizado un estatus con una resta que ya está en los renglones.
 */
enum TransferenciaEstatus: string
{
    case EnTransito = 'en_transito';
    case Recibida = 'recibida';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EnTransito => 'En tránsito',
            self::Recibida => 'Recibida',
        };
    }

    /** Si el material todavía va en el camión: salió del origen y no llegó. */
    public function vaEnCamino(): bool
    {
        return $this === self::EnTransito;
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
