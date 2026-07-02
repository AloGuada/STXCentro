<?php

namespace App\Models\Costos\Concerns;

use App\Enums\Costos\RubroAfectadoEstatus;
use App\Models\Costos\RubroAfectado;
use Illuminate\Support\Carbon;

/**
 * Determina si el documento tiene presupuesto reservado vigente, es decir, al
 * menos un RubroAfectado en estado Apartado cuyo `apartado_hasta` no ha vencido.
 * Lo usa la cadena de aprobaciones para decidir si saltar niveles marcados.
 */
trait VerificaPresupuestoReservado
{
    public function tienePresupuestoReservado(): bool
    {
        return RubroAfectado::query()
            ->where('entrada_type', static::class)
            ->where('entrada_id', $this->getKey())
            ->where('estatus', RubroAfectadoEstatus::Apartado->value)
            ->whereDate('apartado_hasta', '>=', Carbon::today())
            ->exists();
    }

    /**
     * IDs de los centros de costo (rubros) que toca el documento, vía sus
     * partidas → obra_rubro → rubro.
     *
     * @return list<int>
     */
    public function centrosDeCostoIds(): array
    {
        return $this->detalles()
            ->with('obraRubro:id,rubro_id')
            ->get()
            ->pluck('obraRubro.rubro_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
