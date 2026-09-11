<?php

namespace App\Services\Qal;

use App\Enums\Qal\EstatusInspeccion;
use Illuminate\Support\Collection;

/**
 * Cómo lee el tablero una fila de inspección: qué es veredicto, qué es salir
 * bien, cuál es la última palabra de cada pieza-etapa y cómo se agrupa una
 * pieza con su historia. Lo comparten todas sus cuentas para que ninguna lo
 * defina a su manera.
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

    /**
     * Cada pieza con su historia, de la primera inspección a la última.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return Collection<int, array{primera: array<string, mixed>, ultima: array<string, mixed>, rechazos: int, conVeredicto: bool}>
     */
    protected function porPieza(Collection $filas): Collection
    {
        return $filas
            ->groupBy('pieza')
            ->map(fn (Collection $historia): array => [
                'primera' => $historia->first(),
                'ultima' => $historia->last(),
                'rechazos' => $historia->where('estatus', EstatusInspeccion::Rechazado->value)->count(),
                'conVeredicto' => $historia->contains(fn (array $fila): bool => $this->tieneVeredicto($fila)),
            ])
            ->values();
    }

    /**
     * Piezas con al menos un rechazo ÷ piezas: la lectura de gerencia.
     *
     * @param  Collection<int, array<string, mixed>>  $piezas
     */
    protected function tasaDeRechazo(Collection $piezas): ?float
    {
        return $this->porcentaje($piezas->where('rechazos', '>', 0)->count(), $piezas->count());
    }

    /** Con un decimal, o nulo cuando no hay base. */
    protected function porcentaje(int $parte, int $total): ?float
    {
        return $total > 0 ? round($parte * 100 / $total, 1) : null;
    }
}
