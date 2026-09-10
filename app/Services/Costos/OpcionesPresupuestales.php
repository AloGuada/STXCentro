<?php

namespace App\Services\Costos;

use App\Models\Costos\Presupuesto;
use Illuminate\Support\Collection;

/**
 * Los presupuestos como opciones de selector, para todo formulario que cargue
 * gasto a un centro de costos.
 *
 * Desde que el presupuesto es polimórfico (proyecto, obra o partida), elegir
 * primero la obra deja fuera a los centros de costos que cuelgan de un
 * proyecto o de una partida: ésos tienen `obra_id` en null. El selector de
 * cabecera es el presupuesto, y de ahí salen sus rubros por `presupuesto_id`.
 * Requisiciones ya lo hacía así; Solicitudes de pago se quedó filtrando por
 * obra y por eso "no cargaba" los centros de costos nuevos.
 */
class OpcionesPresupuestales
{
    /**
     * @return Collection<int, array{id: int, label: string, cerrado: bool}>
     */
    public function presupuestos(): Collection
    {
        return Presupuesto::with('presupuestable')
            ->get()
            ->map(function (Presupuesto $p): array {
                // OP y descripción internas del presupuesto; cada una cae a la
                // de cobranza (número/descripción del presupuestable) si falta.
                $partes = array_filter([$p->opMostrar(), $p->descripcionMostrar()]);
                $label = implode(' - ', $partes);

                return [
                    'id' => $p->id,
                    'label' => $label !== '' ? $label : $p->nombreMostrar(),
                    'cerrado' => $p->estaCerrado(),
                ];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
