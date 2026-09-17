<?php

namespace App\Models\Qal\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lo que comparten los catálogos de Calidad.
 *
 * Son listas cortas que alimentan los desplegables de captura. Dos reglas
 * heredadas del sistema que se está migrando y que conviene no perder:
 *
 * - **Nada se borra, se desactiva.** Un valor desactivado sale de los
 *   desplegables pero no toca los registros que ya lo mencionan. Por eso
 *   ningún catálogo tiene `destroy`.
 * - **Orden alfabético natural.** `PJ-CM1-10` va después de `PJ-CM1-5`, no entre
 *   el 1 y el 2. Buscar un valor entre 86 soldadores sólo es rápido si el orden
 *   es el que uno espera.
 */
trait EsCatalogoDeCalidad
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * El campo por el que se ordena y se busca. Casi todos usan `nombre`; los
     * que no, lo sobrescriben (los tipos de pieza se ordenan por prefijo).
     */
    public static function campoOrden(): string
    {
        return 'nombre';
    }

    /**
     * Ordena natural en PHP y no en SQL a propósito: MySQL, Postgres y SQLite
     * ordenan los números dentro del texto de tres formas distintas, y estas
     * listas son lo bastante cortas para que el costo no exista.
     *
     * @param  Collection<int, static>  $filas
     * @return Collection<int, static>
     */
    public static function ordenNatural(Collection $filas): Collection
    {
        return $filas->sortBy(static::campoOrden(), SORT_NATURAL | SORT_FLAG_CASE)->values();
    }
}
