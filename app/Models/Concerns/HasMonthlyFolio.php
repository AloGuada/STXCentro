<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Generates a monthly folio on creation: {PREFIX}-YYYYMM## (e.g. OC-20260401).
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

    public function generateMonthlyFolio(): string
    {
        $prefix = sprintf('%s-%s', static::$folioPrefix, now()->format('Ym'));

        $last = DB::table($this->getTable())
            ->where('folio', 'like', "{$prefix}%")
            ->max('folio');

        $next = $last ? ((int) substr($last, -2)) + 1 : 1;

        return sprintf('%s%02d', $prefix, $next);
    }
}
