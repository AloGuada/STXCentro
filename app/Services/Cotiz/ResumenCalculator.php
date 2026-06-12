<?php

namespace App\Services\Cotiz;

use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ResumenColumna;
use App\Models\Cotiz\ResumenFila;
use App\Models\Cotiz\Tarjeta;

/**
 * Cálculo del Resumen de Proyecto (M038). Port de `src/lib/resumen.ts`.
 *
 * Para cada columna (= una tarjeta) se agregan los totales de la tarjeta y se evalúa
 * cada fila según su `tipo_formula`. Los coeficientes vienen del catálogo global con
 * override por (obra, fila) y opcional por (obra, fila, columna).
 *
 * La matriz se resuelve en pasadas: 1) celdas base; 2-4) subtotales, margen y total
 * (que dependen de las filas anteriores del mismo bloque).
 */
class ResumenCalculator
{
    /**
     * Mapeo de `referencia_extra` (EXPL_MO_F<row>) de resumen_filas → código de grupo de
     * los subtotales de Fletes y Viáticos.
     */
    public const EXTRA_CODE_BY_REF = [
        'EXPL_MO_F19' => 'VIATICOS',
        'EXPL_MO_F33' => 'ENERGIA',
        'EXPL_MO_F37' => 'VARIOS',
        'EXPL_MO_F54' => 'FLETES',
        'EXPL_MO_F63' => 'GRUAS',
        'EXPL_MO_F70' => 'PLATAFORMAS',
        'EXPL_MO_F75' => 'LABORATORIO',
        'EXPL_MO_F80' => 'TOPOGRAFIA',
    ];

    public function __construct(
        private readonly TarjetaCalculator $tarjetaCalculator,
        private readonly FletesViaticosSubtotales $fvSubtotales,
    ) {}

    /**
     * Coeficiente efectivo de una celda. Prioridad: override-celda → override-fila → coef_default.
     *
     * @param  array<int, float>  $porFila  [fila_id => coef]
     * @param  array<string, float>  $porCelda  ["fila_id-columna_id" => coef]
     */
    public function coefEfectivo(int $filaId, ?float $coefDefault, int $columnaId, array $porFila, array $porCelda): float
    {
        $k = "{$filaId}-{$columnaId}";
        if (array_key_exists($k, $porCelda)) {
            return $porCelda[$k];
        }
        if (array_key_exists($filaId, $porFila)) {
            return $porFila[$filaId];
        }

        return $coefDefault ?? 0.0;
    }

    /**
     * Resuelve la matriz completa (fila × columna).
     *
     * @param  list<array{id: int, bloque: string, tipo_formula: string, coef_default: ?float, referencia_extra: ?string, orden: int}>  $filas
     * @param  list<array{columna_id: int, kg: float, m2_pintura: float, m2_montaje: float, importe_materiales: float, sueldo_mo_pza: float}>  $columnas
     * @param  array{kg_total: float, m2_montaje_total: float, extras: array<string, float>}  $obra
     * @param  array{por_fila: array<int, float>, por_celda: array<string, float>}  $overrides
     * @return array<int, array<int, float>> [fila_id => [columna_id => importe]]
     */
    public function calcularMatriz(array $filas, array $columnas, array $obra, array $overrides): array
    {
        usort($filas, fn ($a, $b) => $a['orden'] <=> $b['orden']);

        $resultado = [];
        foreach ($filas as $f) {
            $resultado[$f['id']] = [];
        }

        // Pasada 1: celdas base (no dependientes).
        foreach ($filas as $f) {
            foreach ($columnas as $c) {
                $coef = $this->coefEfectivo($f['id'], $f['coef_default'], $c['columna_id'], $overrides['por_fila'], $overrides['por_celda']);
                $tieneCeldaOverride = array_key_exists("{$f['id']}-{$c['columna_id']}", $overrides['por_celda']);
                $base = $this->importeBase($f, $c, $obra, $coef, $tieneCeldaOverride);
                if ($base !== null) {
                    $resultado[$f['id']][$c['columna_id']] = $base;
                }
            }
        }

        // Pasadas 2-4: subtotales ACUMULATIVOS (checkpoints), margen y total.
        // Todas las filas base (MO_FAB, MO_MONTAJE, EXTRAS) suman a un acumulado corriente; cada
        // `subtotal` muestra ese acumulado (p. ej. SUBTOTAL = Σ MO; COSTO DIRECTO = Σ MO + Σ EXTRAS).
        // `margen` = acumulado × coef; `total` = acumulado + margen.
        foreach ($columnas as $c) {
            $acumulado = 0.0;
            $margen = 0.0;
            foreach ($filas as $f) {
                if ($f['tipo_formula'] === 'subtotal') {
                    $resultado[$f['id']][$c['columna_id']] = $acumulado;

                    continue;
                }
                if ($f['tipo_formula'] === 'margen') {
                    $coef = $this->coefEfectivo($f['id'], $f['coef_default'], $c['columna_id'], $overrides['por_fila'], $overrides['por_celda']);
                    $margen = $acumulado * $coef;
                    $resultado[$f['id']][$c['columna_id']] = $margen;

                    continue;
                }
                if ($f['tipo_formula'] === 'total') {
                    $resultado[$f['id']][$c['columna_id']] = $acumulado + $margen;

                    continue;
                }

                // Filas base: acumulan al costo directo corriente.
                $acumulado += $resultado[$f['id']][$c['columna_id']] ?? 0.0;
            }
        }

        return $resultado;
    }

    /**
     * Importe base de una celda según su tipo de fórmula. Devuelve null si depende de otras
     * filas (subtotal/margen/total — se resuelven en las pasadas posteriores).
     *
     * @param  array{id: int, bloque: string, tipo_formula: string, coef_default: ?float, referencia_extra: ?string, orden: int}  $fila
     * @param  array{columna_id: int, kg: float, m2_pintura: float, m2_montaje: float, importe_materiales: float, sueldo_mo_pza: float}  $col
     * @param  array{kg_total: float, m2_montaje_total: float, extras: array<string, float>}  $obra
     */
    private function importeBase(array $fila, array $col, array $obra, float $coef, bool $tieneCeldaOverride): ?float
    {
        switch ($fila['tipo_formula']) {
            case 'materiales':
                return $col['importe_materiales'];
            case 'por_kg':
                return $coef * $col['kg'];
            case 'por_m2_pintura':
                return $coef * $col['m2_pintura'];
            case 'mo_fab_subgrupo':
                // Con override por celda el usuario tecleó el $/kg directo; sin override, coef × kg × sueldo.
                return $tieneCeldaOverride ? $coef * $col['kg'] : $coef * $col['kg'] * $col['sueldo_mo_pza'];
            case 'flete_kg_prorrateado':
                if ($obra['kg_total'] <= 0 || $fila['referencia_extra'] === null) {
                    return 0.0;
                }

                return ($col['kg'] / $obra['kg_total']) * ($obra['extras'][$fila['referencia_extra']] ?? 0.0) * $coef;
            case 'viatico_m2_prorrateado':
                if ($obra['m2_montaje_total'] <= 0 || $fila['referencia_extra'] === null) {
                    return 0.0;
                }

                return ($col['m2_montaje'] / $obra['m2_montaje_total']) * ($obra['extras'][$fila['referencia_extra']] ?? 0.0) * $coef;
            default: // subtotal, margen, total
                return null;
        }
    }

    /**
     * Ensambla todos los datos del resumen de una obra y resuelve la matriz.
     *
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     columnas: list<array<string, mixed>>,
     *     matriz: array<int, array<int, float>>,
     *     totales_por_fila: array<int, float>,
     *     obra_totales: array{kg_total: float, m2_montaje_total: float},
     *     importe_total_venta: float
     * }
     */
    public function calcular(Obra $obra): array
    {
        $filasModelo = ResumenFila::query()->orderBy('orden')->orderBy('id')->get();
        $filas = $filasModelo->map(fn (ResumenFila $f) => [
            'id' => $f->id,
            'descripcion' => $f->descripcion,
            'bloque' => $f->bloque->value,
            'tipo_formula' => $f->tipo_formula->value,
            'coef_default' => $f->coef_default !== null ? (float) $f->coef_default : null,
            'referencia_extra' => $f->referencia_extra,
            'orden' => $f->orden,
            'bloqueada' => $f->bloqueada,
        ])->all();

        $columnasModelo = $obra->resumenColumnas()
            ->with('tarjetas:id,columna_id,tarjeta_id')
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        $columnas = $columnasModelo->map(function (ResumenColumna $col) {
            $kg = 0.0;
            $m2 = 0.0;
            $importe = 0.0;
            foreach ($col->tarjetas as $link) {
                $tarjeta = Tarjeta::find($link->tarjeta_id);
                if ($tarjeta === null) {
                    continue;
                }
                $r = $this->tarjetaCalculator->calcular($tarjeta);
                $kg += $r['kg_reales_total'];
                $importe += $r['total_importe'];
                $m2 += $r['area_pintura'];
            }

            return [
                'columna_id' => $col->id,
                'nombre' => $col->nombre,
                'orden' => $col->orden,
                'kg' => $kg,
                'm2_pintura' => $m2,
                'm2_montaje' => $m2, // aproximación: aún no hay link tarjeta→sección
                'importe_materiales' => $importe,
                'sueldo_mo_pza' => $col->sueldo_mo_pza !== null ? (float) $col->sueldo_mo_pza : 0.0,
            ];
        })->all();

        $kgTotal = array_sum(array_column($columnas, 'kg'));
        $m2MontajeTotal = array_sum(array_column($columnas, 'm2_montaje'));

        $extras = $this->extras($obra);

        $overrides = [
            'por_fila' => $obra->resumenCoeficientes()->get()->mapWithKeys(fn ($c) => [$c->fila_id => (float) $c->coef])->all(),
            'por_celda' => $obra->resumenCeldaOverrides()->get()->mapWithKeys(fn ($c) => ["{$c->fila_id}-{$c->columna_id}" => (float) $c->coef])->all(),
        ];

        $matriz = $this->calcularMatriz(
            array_map(fn ($f) => [
                'id' => $f['id'],
                'bloque' => $f['bloque'],
                'tipo_formula' => $f['tipo_formula'],
                'coef_default' => $f['coef_default'],
                'referencia_extra' => $f['referencia_extra'],
                'orden' => $f['orden'],
            ], $filas),
            $columnas,
            ['kg_total' => $kgTotal, 'm2_montaje_total' => $m2MontajeTotal, 'extras' => $extras],
            $overrides,
        );

        $totalesPorFila = [];
        foreach ($matriz as $filaId => $celdas) {
            $totalesPorFila[$filaId] = array_sum($celdas);
        }

        $filaTotal = collect($filas)->firstWhere('tipo_formula', 'total');
        $importeTotalVenta = $filaTotal !== null ? ($totalesPorFila[$filaTotal['id']] ?? 0.0) : 0.0;

        return [
            'filas' => $filas,
            'columnas' => $columnas,
            'matriz' => $matriz,
            'totales_por_fila' => $totalesPorFila,
            'overrides' => $overrides,
            'obra_totales' => ['kg_total' => $kgTotal, 'm2_montaje_total' => $m2MontajeTotal],
            'importe_total_venta' => $importeTotalVenta,
        ];
    }

    /**
     * Extras de la obra: subtotal de cada grupo de Fletes y Viáticos, indexado por `referencia_extra`.
     *
     * @return array<string, float>
     */
    public function extras(Obra $obra): array
    {
        $subtotales = $this->fvSubtotales->calcular($obra)['subtotales'];

        $extras = [];
        foreach (self::EXTRA_CODE_BY_REF as $ref => $grupo) {
            $extras[$ref] = $subtotales[$grupo] ?? 0.0;
        }

        return $extras;
    }
}
