<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Generates a monthly folio on creation: {PREFIX}-YYMM## (e.g. OC-260401).
 * Sequence resets each month. Model must declare `protected static string $folioPrefix`.
 */
trait HasMonthlyFolio
{
    public static function bootHasMonthlyFolio(): void
    {
        static::creating(function ($model) {
            if (empty($model->folio)) {
                $model->folio = $model->generateMonthlyFolio();
            }
        });
    }

    /**
     * Corre dentro de una transacción con `lockForUpdate` para serializar la
     * generación: mientras un alta calcula y aparta su folio, otra con el mismo
     * prefijo espera hasta que la primera confirme, evitando folios duplicados
     * por altas concurrentes. Al anidarse en la transacción del controlador el
     * lock se conserva hasta el commit externo (después del INSERT real).
     */
    public function generateMonthlyFolio(): string
    {
        $prefix = sprintf('%s-%s', static::$folioPrefix, now()->format('ym'));

        return DB::transaction(function () use ($prefix): string {
            $max = DB::table($this->getTable())
                ->where('folio', 'like', "{$prefix}%")
                ->lockForUpdate()
                ->pluck('folio')
                ->map(fn (string $folio): int => (int) substr($folio, strlen($prefix)))
                ->max();

            $next = ($max ?? 0) + 1;

            return sprintf('%s%02d', $prefix, $next);
        });
    }
}
