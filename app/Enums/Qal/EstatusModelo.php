<?php

namespace App\Enums\Qal;

/**
 * En qué va la conversión de un IFC. Pendiente es que todavía no se sube al
 * servicio; procesando, que el servicio lo está convirtiendo.
 */
enum EstatusModelo: string
{
    case Pendiente = 'pendiente';
    case Procesando = 'procesando';
    case Listo = 'listo';
    case Error = 'error';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'En cola',
            self::Procesando => 'Procesando',
            self::Listo => 'Listo',
            self::Error => 'Error',
        };
    }

    /** Si la conversión ya terminó, bien o mal. */
    public function terminado(): bool
    {
        return $this === self::Listo || $this === self::Error;
    }
}
