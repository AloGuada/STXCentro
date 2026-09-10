<?php

namespace App\Services\Alm;

use Illuminate\Support\Facades\DB;

/**
 * El siguiente `ART-#####` libre.
 *
 * Se pregunta al maestro y a las dos caras, no sólo a `items`: los códigos que
 * nacieron antes del maestro viven en las tres tablas y un hueco entre ellas
 * sería un código repetido esperando a estrenarse.
 */
class GeneradorCodigoArticulo
{
    private const PREFIJO = 'ART-';

    private const DIGITOS = 5;

    public function siguiente(): string
    {
        return DB::transaction(function (): string {
            $max = collect(['items', 'costos_productos', 'alm_articulos'])
                ->flatMap(fn (string $tabla): array => DB::table($tabla)
                    ->where('codigo', 'like', self::PREFIJO.'%')
                    ->lockForUpdate()
                    ->pluck('codigo')
                    ->all())
                ->map(fn (string $codigo): int => (int) substr($codigo, strlen(self::PREFIJO)))
                ->max();

            return sprintf('%s%0'.self::DIGITOS.'d', self::PREFIJO, ($max ?? 0) + 1);
        });
    }

    public static function esCodigoGenerado(?string $codigo): bool
    {
        return $codigo !== null && preg_match('/^ART-\d+$/i', $codigo) === 1;
    }
}
