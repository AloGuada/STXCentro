<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * La base guarda en UTC y la gente vive en `app.display_timezone`. Todo
 * timestamp que salga a una pantalla como texto pasa por aquí: sin esto, lo
 * capturado después de las 18:00 se enseña con la fecha del día siguiente.
 */
final class HoraLocal
{
    /** `Y-m-d H:i:s` en la zona de presentación, o null si no hay fecha. */
    public static function texto(?CarbonInterface $fecha): ?string
    {
        return $fecha?->copy()->setTimezone(config('app.display_timezone'))->toDateTimeString();
    }

    /**
     * Arranque de un día de operación (00:00 en la zona de presentación), ya
     * en UTC, que es como se guarda `created_at`. Para filtrar "desde".
     */
    public static function inicioDelDia(string $fecha): CarbonInterface
    {
        return Date::parse($fecha, config('app.display_timezone'))->startOfDay()->utc();
    }

    /** Cierre de ese día (23:59:59 local), ya en UTC. Para filtrar "hasta". */
    public static function finDelDia(string $fecha): CarbonInterface
    {
        return Date::parse($fecha, config('app.display_timezone'))->endOfDay()->utc();
    }
}
