<?php

namespace App\Services\Cotiz;

use App\Enums\Cotiz\MetodoFleteEstandar;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraFleteEstandar;
use App\Models\Cotiz\Tarjeta;
use Normalizer;

/**
 * Derivaciones del análisis de Fletes Estándar. Reemplaza en PHP la vista
 * `v_obra_flete_estandar` de prepsim (EXPL. M.O rows 89-117) + el cálculo de camiones.
 *
 * Volumen efectivo (override o calculado según método):
 *  - por_kg:     Σ kilos reales del Análisis (fijas × (1 + Σ porcentuales)) vía KilosRealesCalculator.
 *  - por_piezas: Σ cantidad de tarjeta_registros (ml para tarjetas de remates).
 */
class FleteEstandarDerivations
{
    public function __construct(private readonly KilosRealesCalculator $kilosReales) {}

    /**
     * Resuelve volumen, piezas y camiones de un renglón de flete estándar.
     *
     * @return array{volumen: float, pzas: float, camiones: float}
     */
    public function derivar(ObraFleteEstandar $flete): array
    {
        $flete->loadMissing([
            'tarjeta.categoriasKilos.categoria',
            'tarjeta.kilosReales',
            'tarjeta.registros',
        ]);

        $volumen = $this->volumen($flete);
        [$pzas, $camiones] = $this->camionesYPiezas($flete, $volumen);

        return [
            'volumen' => $volumen,
            'pzas' => $pzas,
            'camiones' => $camiones,
        ];
    }

    /**
     * Camiones agregados por grupo (slug en mayúsculas) + total. Alimenta las variables
     * `camiones_<GRUPO>` y `camiones_total` del contexto de Fletes y Viáticos.
     *
     * @return array<string, float>
     */
    public function camionesPorGrupo(Obra $obra): array
    {
        $obra->loadMissing([
            'fletesEstandar.tarjeta.categoriasKilos.categoria',
            'fletesEstandar.tarjeta.kilosReales',
            'fletesEstandar.tarjeta.registros',
        ]);

        $ctx = ['camiones_total' => 0.0];
        foreach ($obra->fletesEstandar as $flete) {
            $camiones = $this->derivar($flete)['camiones'];
            $ctx['camiones_total'] += $camiones;
            if ($flete->grupo !== null && $flete->grupo !== '') {
                $clave = 'camiones_'.$this->slug($flete->grupo);
                $ctx[$clave] = ($ctx[$clave] ?? 0.0) + $camiones;
            }
        }

        return $ctx;
    }

    private function volumen(ObraFleteEstandar $flete): float
    {
        if ($flete->volumen_override !== null) {
            return (float) $flete->volumen_override;
        }

        $tarjeta = $flete->tarjeta;
        if ($tarjeta === null) {
            return 0.0;
        }

        if ($flete->metodo === MetodoFleteEstandar::PorKg) {
            return $this->volumenPorKg($tarjeta);
        }

        return (float) $tarjeta->registros
            ->whereNotNull('cantidad')
            ->sum(fn ($registro) => (float) $registro->cantidad);
    }

    private function volumenPorKg(Tarjeta $tarjeta): float
    {
        $porTipoCorte = $this->kilosReales->porTipoCorte(
            $tarjeta->categoriasKilos->map(fn ($c) => [
                'categoria_id' => $c->categoria_id,
                'tipo_corte' => $c->categoria?->tipo_corte?->value ?? '',
                'porcentual' => $c->porcentual,
            ])->all(),
            $tarjeta->kilosReales->map(fn ($k) => [
                'categoria_id' => $k->categoria_id,
                'kilos' => $k->kilos,
            ])->all(),
        );

        return $this->kilosReales->total($porTipoCorte);
    }

    /**
     * @return array{0: float, 1: float} [pzas, camiones]
     */
    private function camionesYPiezas(ObraFleteEstandar $flete, float $volumen): array
    {
        if ($flete->metodo === MetodoFleteEstandar::PorKg) {
            $kgPorCamion = (float) $flete->kg_por_camion;
            $camiones = $kgPorCamion > 0.0 ? $this->roundUp($volumen / $kgPorCamion, 2) : 0.0;

            return [0.0, $camiones];
        }

        $mlPorPza = (float) $flete->ml_por_pza;
        $pzas = $mlPorPza > 0.0 ? ceil($volumen / $mlPorPza) : 0.0;
        $pzasPorCamion = (int) $flete->pzas_por_camion;
        $camiones = $pzasPorCamion > 0 ? $this->roundUp($pzas / $pzasPorCamion, 2) : 0.0;

        return [$pzas, $camiones];
    }

    /**
     * ROUNDUP del Excel: redondea hacia arriba en magnitud a `$decimales` decimales.
     */
    private function roundUp(float $x, int $decimales = 0): float
    {
        $factor = 10 ** $decimales;

        return ($x <=> 0) * ceil(abs($x) * $factor) / $factor;
    }

    /**
     * "Estructura" → ESTRUCTURA, "Láminas y remates" → LAMINAS_Y_REMATES.
     */
    private function slug(string $valor): string
    {
        $s = $valor;
        if (class_exists(Normalizer::class)) {
            $descompuesto = Normalizer::normalize($s, Normalizer::FORM_D);
            if ($descompuesto !== false) {
                $s = preg_replace('/\p{Mn}/u', '', $descompuesto) ?? $s;
            }
        }

        $s = strtoupper($s);
        $s = preg_replace('/[^A-Z0-9]+/', '_', $s) ?? $s;

        return trim($s, '_');
    }
}
