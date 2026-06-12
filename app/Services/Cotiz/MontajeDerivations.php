<?php

namespace App\Services\Cotiz;

use App\Models\Cotiz\FaseMontaje;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\SeccionMontaje;
use Illuminate\Support\Collection;

/**
 * Derivaciones del Análisis de Montaje. Reemplaza en PHP las vistas SQL de prepsim
 * `v_seccion_fase_dias`, `v_seccion_fase_nomina`, `v_seccion_fase_importe`,
 * `v_seccion_importe` y `v_obra_semanas_montaje`.
 *
 * Toda la aritmética se hace en PHP con guardas explícitas contra división por cero
 * (PostgreSQL lanza error; no se replica el hack `* 1.0` de SQLite).
 */
class MontajeDerivations
{
    /** Días hábiles por semana de fabricación/montaje (Excel: dias/5.5 = semanas). */
    public const DIAS_POR_SEMANA = 5.5;

    /**
     * Días por fase de una sección = Σ(cantidad / rendimiento), guardando rendimiento > 0.
     *
     * @return array<int, float> [fase_id => dias]
     */
    public function diasPorFase(SeccionMontaje $seccion): array
    {
        $dias = [];
        foreach ($seccion->rendimientos as $rendimiento) {
            $rend = (float) $rendimiento->rendimiento;
            if ($rend <= 0.0) {
                continue;
            }
            $faseId = $rendimiento->fase_id;
            $dias[$faseId] = ($dias[$faseId] ?? 0.0) + (float) $rendimiento->cantidad / $rend;
        }

        return $dias;
    }

    /**
     * Nómina semanal por fase = Σ(cantidad_personas × sueldo_semanal).
     *
     * @return array<int, float> [fase_id => nomina_semanal]
     */
    public function nominaPorFase(SeccionMontaje $seccion): array
    {
        $nomina = [];
        foreach ($seccion->personal as $celda) {
            $sueldo = (float) ($celda->categoria?->sueldo_semanal ?? 0.0);
            $faseId = $celda->fase_id;
            $nomina[$faseId] = ($nomina[$faseId] ?? 0.0) + (float) $celda->cantidad * $sueldo;
        }

        return $nomina;
    }

    /**
     * Importe por fase: semanas = dias / 5.5; importe = nómina × semanas.
     *
     * @return array<int, array{nomina_semanal: float, dias: float, semanas: float, importe: float}>
     */
    public function importePorFase(SeccionMontaje $seccion): array
    {
        $dias = $this->diasPorFase($seccion);
        $nomina = $this->nominaPorFase($seccion);

        $faseIds = array_unique([...array_keys($dias), ...array_keys($nomina)]);

        $resultado = [];
        foreach ($faseIds as $faseId) {
            $d = $dias[$faseId] ?? 0.0;
            $n = $nomina[$faseId] ?? 0.0;
            $semanas = $d / self::DIAS_POR_SEMANA;
            $resultado[$faseId] = [
                'nomina_semanal' => $n,
                'dias' => $d,
                'semanas' => $semanas,
                'importe' => $n * $semanas,
            ];
        }

        return $resultado;
    }

    /**
     * Importe directo (Σ importe por fase) y total (× factor contratista) de una sección.
     *
     * @return array{importe_directo: float, importe_total: float}
     */
    public function importeSeccion(SeccionMontaje $seccion, float $factorContratista): array
    {
        $directo = 0.0;
        foreach ($this->importePorFase($seccion) as $fase) {
            $directo += $fase['importe'];
        }

        return [
            'importe_directo' => $directo,
            'importe_total' => $directo * $factorContratista,
        ];
    }

    /**
     * Semanas de una sección = Σ días (sobre sus fases) / 5.5.
     */
    public function semanasSeccion(SeccionMontaje $seccion): float
    {
        return array_sum($this->diasPorFase($seccion)) / self::DIAS_POR_SEMANA;
    }

    /**
     * Semanas requeridas de la obra = Σ días de todas las secciones / 5.5 (v_obra_semanas_montaje).
     */
    public function semanasRequeridasObra(Obra $obra): float
    {
        $diasTotales = 0.0;
        foreach ($obra->seccionesMontaje as $seccion) {
            $diasTotales += array_sum($this->diasPorFase($seccion));
        }

        return $diasTotales / self::DIAS_POR_SEMANA;
    }

    /**
     * Importe total de montaje de la obra = Σ importe_total de sus secciones (Σ v_seccion_importe).
     */
    public function moMontajeObra(Obra $obra, float $factorContratista): float
    {
        $total = 0.0;
        foreach ($obra->seccionesMontaje as $seccion) {
            $total += $this->importeSeccion($seccion, $factorContratista)['importe_total'];
        }

        return $total;
    }

    /**
     * Días y piezas agregados por fase sobre todas las secciones de la obra. Alimenta las
     * variables `dias_fase_<COD>` / `semanas_fase_<COD>` / `piezas_fase_<COD>` de Fletes y Viáticos.
     *
     * @param  Collection<int, FaseMontaje>  $fases  catálogo completo de fases (incluye las de 0)
     * @return array<int, array{codigo: string, dias: float, piezas: float}> [fase_id => ...]
     */
    public function agregadosPorFaseObra(Obra $obra, Collection $fases): array
    {
        $diasPorFase = [];
        $piezasPorFase = [];

        foreach ($obra->seccionesMontaje as $seccion) {
            foreach ($this->diasPorFase($seccion) as $faseId => $dias) {
                $diasPorFase[$faseId] = ($diasPorFase[$faseId] ?? 0.0) + $dias;
            }
            foreach ($seccion->rendimientos as $rendimiento) {
                $faseId = $rendimiento->fase_id;
                $piezasPorFase[$faseId] = ($piezasPorFase[$faseId] ?? 0.0) + (float) $rendimiento->cantidad;
            }
        }

        $resultado = [];
        foreach ($fases as $fase) {
            $resultado[$fase->id] = [
                'codigo' => $fase->codigo,
                'dias' => $diasPorFase[$fase->id] ?? 0.0,
                'piezas' => $piezasPorFase[$fase->id] ?? 0.0,
            ];
        }

        return $resultado;
    }
}
