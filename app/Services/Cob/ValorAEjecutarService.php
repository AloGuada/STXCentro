<?php

namespace App\Services\Cob;

use App\Models\Cob\Comparativo;
use App\Models\Obra;
use App\Models\Proyecto;
use Illuminate\Support\Collection;

/**
 * Valor a ejecutar de un proyecto: la misma regla que el frontend aplica en
 * `resources/js/components/cob/calculos.ts` (`calcularResumenProyecto`, el
 * bloque de `presupuestoEjecutar`).
 *
 * - Si el proyecto no tiene ningún comparativo ligado a obra → todo por partidas.
 * - Obra a precio unitario → el ÚLTIMO comparativo de esa obra (el de mayor id),
 *   o 0 si esa obra no tiene comparativo: en unitario la comparativa de
 *   ingeniería define el precio final.
 * - Obra a precio alzado (u otro tipo) → siempre sus partidas; el comparativo es
 *   solo referencia porque el monto está pactado fijo.
 *
 * OJO: esta regla está duplicada en TypeScript. Si cambia aquí, hay que cambiar
 * `calculos.ts` y el test de paridad `tests/Feature/Cob/ValorAEjecutarTest.php`.
 */
class ValorAEjecutarService
{
    /** @var list<string> */
    private const EAGER = [
        'obras:id,proyecto_id,tipo,tipo_contrato',
        'obras.partidas:id,obra_id,monto',
        'comparativos:id,proyecto_id,obra_id,monto_impacto',
    ];

    public function paraProyecto(Proyecto $proyecto): float
    {
        $proyecto->loadMissing(self::EAGER);

        return $this->calcular($proyecto);
    }

    /**
     * Versión en lote para pantallas que listan varios proyectos: un eager load
     * por relación, no uno por proyecto.
     *
     * @param  Collection<int, Proyecto>  $proyectos
     * @return array<int, float> [proyecto_id => valor a ejecutar]
     */
    public function paraProyectos(Collection $proyectos): array
    {
        $proyectos->loadMissing(self::EAGER);

        return $proyectos
            ->mapWithKeys(fn (Proyecto $p) => [$p->id => $this->calcular($p)])
            ->all();
    }

    private function calcular(Proyecto $proyecto): float
    {
        $ultimoPorObra = $this->ultimoComparativoPorObra($proyecto);
        $hayComparativos = $ultimoPorObra->isNotEmpty();

        return (float) $proyecto->obras->sum(
            fn (Obra $obra) => $this->valorObra($obra, $ultimoPorObra, $hayComparativos)
        );
    }

    /**
     * El comparativo vigente de cada obra es el de mayor id (no el de fecha más
     * reciente): así lo resuelve el frontend y así se captura en la práctica.
     *
     * @return Collection<int, Comparativo> indexada por obra_id
     */
    private function ultimoComparativoPorObra(Proyecto $proyecto): Collection
    {
        return $proyecto->comparativos
            ->filter(fn (Comparativo $c) => $c->obra_id !== null)
            ->sortByDesc('id')
            ->unique('obra_id')
            ->keyBy('obra_id');
    }

    /**
     * @param  Collection<int, Comparativo>  $ultimoPorObra
     */
    private function valorObra(Obra $obra, Collection $ultimoPorObra, bool $hayComparativos): float
    {
        if (! $hayComparativos) {
            return $this->sumaPartidas($obra);
        }

        if ($obra->tipo_contrato === 'precio_unitario') {
            $comparativo = $ultimoPorObra->get($obra->id);

            return $comparativo ? (float) $comparativo->monto_impacto : 0.0;
        }

        return $this->sumaPartidas($obra);
    }

    private function sumaPartidas(Obra $obra): float
    {
        // `monto` está casteado a decimal:2, así que llega como string.
        return (float) $obra->partidas->sum(fn ($partida) => (float) $partida->monto);
    }
}
