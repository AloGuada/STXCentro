<?php

namespace App\Support;

use Illuminate\Support\Collection;

class Moneda
{
    /**
     * Moneda de un total agregado: si todos los montos comparten una sola
     * divisa, ésa; si se mezclan (o no hay ninguna), MXN. Se usa en totales que
     * suman varios documentos/partidas (comparativo, dashboards, reportes).
     *
     * @param  iterable<string|null>  $monedas
     */
    public static function agregada(iterable $monedas): string
    {
        $distintas = Collection::make($monedas)
            ->filter()
            ->map(fn ($m): string => strtolower((string) $m))
            ->unique()
            ->values();

        return $distintas->count() === 1 ? (string) $distintas->first() : 'mxn';
    }
}
