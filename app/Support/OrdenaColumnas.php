<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Aplica ordenamiento server-side a un query index desde los parámetros
 * `sort_by`/`sort_dir` del request, validando la columna contra una whitelist.
 *
 * Cada valor del mapa `$columnas` es:
 *  - un string: nombre de columna o alias de agregado (withCount/withSum) a ordenar.
 *  - un Closure(Builder $query, string $dir): para columnas de relación que
 *    requieren un subquery/join (ej. ordenar por la razón social del proveedor).
 *
 * El frontend (`DataTable`) usa el `by`/`dir` devuelto para pintar el indicador.
 */
trait OrdenaColumnas
{
    /**
     * @param  Builder<*>  $query
     * @param  array<string, string|Closure>  $columnas
     * @return array{by: string, dir: string}
     */
    protected function aplicarOrden(
        Builder $query,
        Request $request,
        array $columnas,
        string $porDefecto,
        string $dirPorDefecto = 'asc',
    ): array {
        $by = (string) $request->query('sort_by', '');
        $dir = strtolower((string) $request->query('sort_dir', '')) === 'desc' ? 'desc' : 'asc';

        // Sin columna válida en la whitelist: aplicar el orden por defecto. El
        // default se trata como nombre de columna literal (ej. `created_at`),
        // que puede no ser una de las claves ordenables del front.
        if (! array_key_exists($by, $columnas)) {
            $query->orderBy($porDefecto, $dirPorDefecto);

            return ['by' => $porDefecto, 'dir' => $dirPorDefecto];
        }

        $columna = $columnas[$by];

        if ($columna instanceof Closure) {
            $columna($query, $dir);
        } else {
            $query->orderBy($columna, $dir);
        }

        return ['by' => $by, 'dir' => $dir];
    }
}
