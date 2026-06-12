<?php

namespace App\Services\Cotiz;

use App\Models\Cotiz\FaseMontaje;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraFleteViatico;
use Normalizer;

/**
 * Motor de fórmulas de Fletes y Viáticos (M043). Port de `src/lib/fletesViaticos.ts`.
 *
 * Cada item de la obra puede formular su cantidad y/o su p_unit (symfony/expression-language).
 * El resultado se PERSISTE en cantidad/p_unit (patrón cache), de modo que los subtotales y el
 * Resumen leen valores planos. Las fórmulas cruzadas entre items (cantidad_<clave>,
 * p_unit_<clave>, importe_<clave>) se resuelven con un DAG de hasta 5 pasadas.
 */
class FletesViaticosCalculator
{
    public function __construct(
        private readonly CuadrillaGlobalDerivations $cuadrilla,
        private readonly MontajeDerivations $montaje,
        private readonly FleteEstandarDerivations $fleteEstandar,
        private readonly FormulaEvaluator $evaluator,
    ) {}

    /**
     * Construye el mapa de variables de la obra disponibles para las fórmulas.
     *
     * @return array<string, float>
     */
    public function construirContexto(Obra $obra): array
    {
        $obra->loadMissing(['cuadrillaGlobal.categoria', 'seccionesMontaje.rendimientos', 'seccionesMontaje.personal.categoria']);

        $ctx = [];

        // Cuadrilla global: grupos + personas (Excel C12 = total − cabos).
        $cuadrilla = $this->cuadrilla->totales($obra);
        $ctx['grupos'] = (float) $cuadrilla['num_grupos'];
        $ctx['personas_totales'] = (float) $cuadrilla['personas_totales'];
        $ctx['personas'] = (float) $cuadrilla['personas_viaticos'];

        // Tiempo: semanas requeridas → semanas/meses/días como el Excel (D13/D12/C13).
        $semanasReq = $this->montaje->semanasRequeridasObra($obra);
        $ctx['semanas_req'] = $semanasReq;
        $ctx['semanas'] = $this->roundUp($semanasReq, 0);
        $ctx['meses'] = $this->roundUp($ctx['semanas'] / 4.2, 0);
        $ctx['dias'] = $ctx['semanas'] * 6;

        // Totales de obra.
        $factor = (float) $obra->factor_contratista;
        $ctx['kg_obra'] = (float) $obra->tarjetas()->sum('kilos_reales');
        $ctx['mo_montaje'] = $this->montaje->moMontajeObra($obra, $factor);

        // Camiones del análisis de fletes estándar, agregados por grupo (slug) + total.
        foreach ($this->fleteEstandar->camionesPorGrupo($obra) as $clave => $valor) {
            $ctx[$clave] = $valor;
        }

        // Por fase de montaje: días, semanas y piezas agregadas sobre las secciones de la obra.
        $fases = FaseMontaje::query()->get();
        foreach ($this->montaje->agregadosPorFaseObra($obra, $fases) as $fase) {
            $cod = $this->slug($fase['codigo']);
            $ctx["dias_fase_{$cod}"] = $fase['dias'];
            $ctx["semanas_fase_{$cod}"] = $fase['dias'] / MontajeDerivations::DIAS_POR_SEMANA;
            $ctx["piezas_fase_{$cod}"] = $fase['piezas'];
        }

        return $ctx;
    }

    /**
     * Evalúa las fórmulas de todos los items de la obra y persiste cantidad/p_unit cuando difieren.
     * Devuelve true si escribió algo.
     */
    public function recalcular(Obra $obra): bool
    {
        $items = $obra->fletesViaticos()->get();
        if ($items->isEmpty()) {
            return false;
        }

        $ctx = $this->construirContexto($obra);
        $resueltos = $this->evaluarItems(
            $items->map(fn (ObraFleteViatico $i) => [
                'id' => $i->id,
                'clave' => $i->clave,
                'cantidad' => (float) $i->cantidad,
                'p_unit' => (float) $i->p_unit,
                'formula_cantidad' => $i->formula_cantidad,
                'formula_p_unit' => $i->formula_p_unit,
            ])->all(),
            $ctx,
        );

        $hubo = false;
        foreach ($items as $item) {
            $r = $resueltos[$item->id] ?? null;
            if ($r === null) {
                continue;
            }
            if (abs($r['cantidad'] - (float) $item->cantidad) > 1e-9 || abs($r['p_unit'] - (float) $item->p_unit) > 1e-9) {
                $item->forceFill([
                    'cantidad' => $r['cantidad'],
                    'p_unit' => $r['p_unit'],
                ])->save();
                $hubo = true;
            }
        }

        return $hubo;
    }

    /**
     * Núcleo puro: resuelve cantidad/p_unit por item (DAG, máx 5 pasadas). Lado sin fórmula =
     * valor guardado. Items irresolubles (ciclo o variable inexistente) conservan su valor.
     *
     * @param  list<array{id: int, clave: ?string, cantidad: float, p_unit: float, formula_cantidad: ?string, formula_p_unit: ?string}>  $items
     * @param  array<string, float>  $ctxBase
     * @return array<int, array{cantidad: float, p_unit: float}>
     */
    public function evaluarItems(array $items, array $ctxBase): array
    {
        $resueltos = [];
        $ctx = $ctxBase;

        for ($pasada = 0; $pasada < 5; $pasada++) {
            $cambios = 0;
            foreach ($items as $item) {
                if (isset($resueltos[$item['id']])) {
                    continue;
                }
                $cant = ($item['formula_cantidad'] !== null && $item['formula_cantidad'] !== '')
                    ? $this->evaluator->evaluar($item['formula_cantidad'], $ctx)
                    : $item['cantidad'];
                $pu = ($item['formula_p_unit'] !== null && $item['formula_p_unit'] !== '')
                    ? $this->evaluator->evaluar($item['formula_p_unit'], $ctx)
                    : $item['p_unit'];

                if ($cant === null || $pu === null) {
                    continue; // variable aún no definida — reintentar en otra pasada
                }

                $resueltos[$item['id']] = ['cantidad' => $cant, 'p_unit' => $pu];
                $cambios++;
                if ($item['clave'] !== null && $item['clave'] !== '') {
                    $ctx["cantidad_{$item['clave']}"] = $cant;
                    $ctx["p_unit_{$item['clave']}"] = $pu;
                    $ctx["importe_{$item['clave']}"] = $cant * $pu;
                }
            }
            if ($cambios === 0) {
                break;
            }
        }

        // Irresolubles: conservar lo guardado (no escribir basura).
        foreach ($items as $item) {
            if (! isset($resueltos[$item['id']])) {
                $resueltos[$item['id']] = ['cantidad' => $item['cantidad'], 'p_unit' => $item['p_unit']];
            }
        }

        return $resueltos;
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
