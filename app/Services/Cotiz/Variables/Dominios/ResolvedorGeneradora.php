<?php

namespace App\Services\Cotiz\Variables\Dominios;

use App\Models\Cotiz\Generadora;
use App\Models\Cotiz\GeneradoraRegistro;
use App\Models\Cotiz\ObraInsumoOverride;
use App\Services\Cotiz\MermaCalculator;
use App\Services\Cotiz\Variables\ContextoEval;
use App\Services\Cotiz\Variables\Direccion;
use App\Services\Cotiz\Variables\ResolvedorDominio;
use RuntimeException;

/**
 * Dominio `generadora`: `kg` (con merma) y `kg_real` (sin merma), opcionalmente filtrados por
 * marca. Port de `src/lib/variables/dominios/generadora.ts`.
 *
 * Replica el pie de la generadora: kilos_reales = t_ml_m2 × peso_lineal efectivo (obra override
 * > global); los kilos con merma evalúan la fórmula de la merma + el peso_porcentual agregado
 * por material.
 */
final class ResolvedorGeneradora implements ResolvedorDominio
{
    public function __construct(private readonly MermaCalculator $merma) {}

    public function dominio(): string
    {
        return 'generadora';
    }

    public function resolver(Direccion $dir, ContextoEval $ctx): float
    {
        if ($dir->op !== 'total') {
            throw new RuntimeException("generadora.{$dir->columna} solo admite 'total'");
        }
        if ($dir->filtro !== null && $dir->filtro['clave'] !== 'marca') {
            throw new RuntimeException("filtro no válido para generadora: {$dir->filtro['clave']}");
        }

        $ids = $this->resolverIds($dir, $ctx->obraId);
        ['kg' => $kg, 'kgReal' => $kgReal] = $this->agregados($ids, $ctx->obraId, $dir->filtro['valor'] ?? null);

        if ($dir->columna === 'kg') {
            return $kg;
        }
        if ($dir->columna === 'kg_real') {
            return $kgReal;
        }

        throw new RuntimeException("columna desconocida: generadora.{$dir->columna}");
    }

    /**
     * @return list<int>
     */
    private function resolverIds(Direccion $dir, ?int $obraId): array
    {
        $query = Generadora::query()->where('obra_id', $obraId);

        if ($dir->instancia !== null) {
            $ids = $query->where('titulo', $dir->instancia)->pluck('id')->all();
            if ($ids === []) {
                throw new RuntimeException("generadora no encontrada: \"{$dir->instancia}\"");
            }

            return $ids;
        }

        return $query->pluck('id')->all();
    }

    /**
     * @param  list<int>  $generadoraIds
     * @return array{kg: float, kgReal: float}
     */
    private function agregados(array $generadoraIds, ?int $obraId, ?string $marca): array
    {
        if ($generadoraIds === []) {
            return ['kg' => 0.0, 'kgReal' => 0.0];
        }

        $query = GeneradoraRegistro::query()
            ->whereIn('generadora_id', $generadoraIds)
            ->with('materialOrigen', 'merma');
        if ($marca !== null) {
            $query->where('marca', $marca);
        }
        $registros = $query->get();

        $overrides = ObraInsumoOverride::query()
            ->where('obra_id', $obraId)
            ->get()
            ->keyBy('insumo_id');

        // peso_porcentual agregado por material (igual que el pie de la generadora).
        $agg = [];
        $datos = [];
        foreach ($registros as $registro) {
            $insumo = $registro->materialOrigen;
            $override = $insumo !== null ? $overrides->get($insumo->id) : null;
            $pesoLineal = $this->coalesce($override?->peso_lineal, $insumo?->peso_lineal);
            $pesoDefault = $this->coalesce($override?->peso_default, $insumo?->peso_default);

            $tMlM2 = $registro->t_ml_m2;
            $kilosReales = ($tMlM2 !== null && $pesoLineal !== null) ? $tMlM2 * $pesoLineal : 0.0;
            $kilosConMerma = $this->kilosConMerma($registro, $kilosReales, $pesoLineal, $pesoDefault);

            $datos[] = [
                'material' => $insumo?->id,
                'kilos_reales' => $kilosReales,
                'kilos_con_merma' => $kilosConMerma,
                'peso_porcentual' => $registro->peso_porcentual !== null ? (float) $registro->peso_porcentual : null,
                'kilos_totales' => $registro->kilos_totales !== null ? (float) $registro->kilos_totales : null,
            ];

            if ($insumo !== null) {
                $agg[$insumo->id] ??= ['reales' => 0.0, 'conMerma' => 0.0];
                $agg[$insumo->id]['reales'] += $kilosReales;
                $agg[$insumo->id]['conMerma'] += $kilosConMerma;
            }
        }

        $kg = 0.0;
        $kgReal = 0.0;
        foreach ($datos as $registro) {
            $real = $registro['kilos_reales'];
            $kgReal += $real;

            $pctCalc = 0.0;
            if ($registro['material'] !== null) {
                $a = $agg[$registro['material']];
                if ($a['reales'] > 0) {
                    $pctCalc = ($a['conMerma'] - $a['reales']) / $a['reales'];
                }
            }
            $pctEf = $registro['peso_porcentual'] ?? $pctCalc;
            $kg += $registro['kilos_totales'] ?? $real * (1 + $pctEf);
        }

        return ['kg' => $kg, 'kgReal' => $kgReal];
    }

    private function kilosConMerma(GeneradoraRegistro $registro, float $kilosReales, ?float $pesoLineal, ?float $pesoDefault): float
    {
        $formula = $registro->merma?->formula;
        if ($formula === null || trim($formula) === '') {
            return $kilosReales;
        }

        return $this->merma->evaluar($formula, [
            't_ml_m2' => $registro->t_ml_m2,
            'kilos_reales' => $kilosReales,
            'ancho' => $registro->ancho,
            'largo' => $registro->largo,
            'cantidad' => $registro->cantidad,
            'cant_pzas' => $registro->cant_pzas,
            'peso_lineal' => $pesoLineal,
            'peso_default' => $pesoDefault,
        ]);
    }

    private function coalesce(int|float|string|null $override, int|float|string|null $global): ?float
    {
        if ($override !== null && $override !== '') {
            return (float) $override;
        }

        return $global !== null ? (float) $global : null;
    }
}
