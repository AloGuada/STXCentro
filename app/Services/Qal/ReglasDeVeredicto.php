<?php

namespace App\Services\Qal;

use App\Enums\Qal\EstatusInspeccion;
use Illuminate\Support\Collection;

/**
 * Cómo lee el tablero una fila de inspección: qué es veredicto, qué es salir
 * bien y cuál es la última palabra de cada pieza-etapa. Lo comparten todas sus
 * cuentas para que ninguna lo defina a su manera.
 *
 * En armado y vestido no existe «liberado»: la pieza que sale bien queda
 * pendiente porque avanza a soldado, así que ahí pendiente es veredicto.
 */
trait ReglasDeVeredicto
{
    /** @param  array<string, mixed>  $fila */
    protected function tieneVeredicto(array $fila): bool
    {
        return $fila['armado'] || $fila['estatus'] !== EstatusInspeccion::Pendiente->value;
    }

    /** @param  array<string, mixed>  $fila */
    protected function salioBien(array $fila): bool
    {
        return $fila['estatus'] === EstatusInspeccion::Liberado->value
            || ($fila['armado'] && $fila['estatus'] === EstatusInspeccion::Pendiente->value);
    }

    /**
     * La última inspección de cada pieza-etapa: la que dice cómo acabó.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return Collection<int, array<string, mixed>>
     */
    protected function ultimaPorEtapa(Collection $filas): Collection
    {
        return $filas
            ->groupBy('etapa')
            ->map(fn (Collection $historia): array => $historia->sortBy('inspeccion')->last())
            ->values();
    }

    /** Con un decimal, o nulo cuando no hay base. */
    protected function porcentaje(int $parte, int $total): ?float
    {
        return $total > 0 ? round($parte * 100 / $total, 1) : null;
    }
}
