<?php

namespace App\Services\Prod;

use App\Models\Concepto;
use App\Models\Prod\Registro;
use Illuminate\Support\Collection;

/**
 * Cuánto se ha pagado de cada pieza contra lo que el catálogo vigente manda
 * fabricar.
 *
 * La unidad es la **pieza equivalente**: capturar 10 piezas al 60% consume 6 y
 * deja 4 para liquidarse en un destajo posterior. Así el mismo lote se puede
 * pagar en parcialidades sin rebasar nunca lo que pide el catálogo.
 *
 * El acumulado se cuenta **por marca dentro de la obra, sumando todas las
 * versiones del catálogo**: al versionar, las piezas copiadas son filas nuevas
 * sin registros propios, así que contarlas por `concepto_id` dejaría el avance
 * en cero y permitiría volver a pagar lo ya fabricado.
 */
class AvanceDePiezas
{
    /** Margen para no rechazar por ruido de redondeo del porcentaje. */
    private const EPSILON = 0.0001;

    /**
     * Piezas equivalentes ya pagadas por marca, en toda la historia de la obra.
     *
     * @return Collection<string, float>
     */
    public function capturadoPorMarca(int $obraId): Collection
    {
        return Registro::query()
            ->with('concepto:id,marca')
            ->whereHas('concepto', fn ($q) => $q->where('obra_id', $obraId))
            ->get(['id', 'concepto_id', 'cantidad', 'porcentaje'])
            ->groupBy(fn (Registro $registro) => $registro->concepto?->marca)
            ->map(fn (Collection $registros) => round(
                $registros->sum(fn (Registro $registro) => $registro->piezasEquivalentes()),
                4
            ));
    }

    /**
     * Piezas equivalentes que todavía se pueden pagar de este concepto. Nunca
     * negativo: si el catálogo se recortó por debajo de lo ya pagado, queda 0.
     */
    public function disponible(Concepto $concepto): float
    {
        return round(max(0, (float) $concepto->cantidad - $this->capturado($concepto)), 4);
    }

    /** Piezas equivalentes ya pagadas de esta marca en la obra. */
    public function capturado(Concepto $concepto): float
    {
        return (float) $this->capturadoPorMarca($concepto->obra_id)->get($concepto->marca, 0.0);
    }

    /** ¿Cabe pagar esta cantidad a este porcentaje sin rebasar el catálogo? */
    public function cabe(Concepto $concepto, int $cantidad, float $porcentaje): bool
    {
        $consumo = round($cantidad * ($porcentaje / 100), 4);

        return $consumo <= $this->disponible($concepto) + self::EPSILON;
    }

    /**
     * Decora una colección de conceptos con su avance, sin consultar la BD una
     * vez por pieza.
     *
     * @param  Collection<int, Concepto>  $conceptos
     * @return Collection<int, Concepto>
     */
    public function decorar(Collection $conceptos): Collection
    {
        $porObra = $conceptos
            ->pluck('obra_id')
            ->unique()
            ->mapWithKeys(fn (int $obraId) => [$obraId => $this->capturadoPorMarca($obraId)]);

        return $conceptos->each(function (Concepto $concepto) use ($porObra): void {
            $capturado = (float) $porObra->get($concepto->obra_id)?->get($concepto->marca, 0.0);

            $concepto->setAttribute('capturado', $capturado);
            $concepto->setAttribute('disponible', round(max(0, (float) $concepto->cantidad - $capturado), 4));
        });
    }
}
