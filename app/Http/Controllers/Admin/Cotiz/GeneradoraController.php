<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\GeneradoraReorderRequest;
use App\Http\Requests\Admin\Cotiz\GeneradoraStoreRequest;
use App\Http\Requests\Admin\Cotiz\GeneradoraUpdateRequest;
use App\Models\Cotiz\Generadora;
use App\Models\Cotiz\GeneradoraRegistro;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\Merma;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraInsumoOverride;
use App\Services\Cotiz\MermaCalculator;
use App\Services\Cotiz\OverrideResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GeneradoraController extends Controller
{
    public function index(Request $request, Obra $obra): Response
    {
        $me = $request->user()?->id;

        $generadoras = $obra->generadoras()
            ->withCount('registros')
            ->with('lockedBy:id,name')
            ->orderBy('orden')
            ->get()
            ->map(function (Generadora $generadora) use ($me) {
                // El lock propio no se muestra como bloqueo (uno re-entra a lo suyo); solo el de otro.
                $bloqueadaPorOtro = $me !== null && $generadora->isLocked() && ! $generadora->isLockedBy($me);

                return [
                    'id' => $generadora->id,
                    'obra_id' => $generadora->obra_id,
                    'titulo' => $generadora->titulo,
                    'orden' => $generadora->orden,
                    'registros_count' => $generadora->registros_count,
                    'is_locked' => $bloqueadaPorOtro,
                    'locked_at' => $bloqueadaPorOtro ? $generadora->locked_at?->toIso8601String() : null,
                    'locked_by' => $bloqueadaPorOtro ? $generadora->lockedBy?->only(['id', 'name']) : null,
                ];
            });

        return Inertia::render('admin/cotiz/generadoras/index', [
            'obra' => $obra,
            'generadoras' => $generadoras,
        ]);
    }

    public function store(GeneradoraStoreRequest $request): RedirectResponse
    {
        $generadora = Generadora::create($request->validated());

        return to_route('admin.cotiz.generadoras.index', $generadora->obra_id);
    }

    public function edit(Generadora $generadora, MermaCalculator $mermaCalculator, OverrideResolver $resolver): Response
    {
        $generadora->load(['obra', 'lockedBy:id,name']);

        // Overrides de insumo por obra (Fase 2): los pesos efectivos salen de override > global.
        $overrides = ObraInsumoOverride::query()
            ->where('obra_id', $generadora->obra_id)
            ->get()
            ->keyBy('insumo_id');

        // Pasada 1: pesos/kilos efectivos por registro (con override por obra aplicado).
        $calc = $generadora->registros()
            ->with(['materialOrigen', 'merma'])
            ->orderBy('id')
            ->get()
            ->map(function (GeneradoraRegistro $registro) use ($mermaCalculator, $resolver, $overrides) {
                $insumo = $registro->materialOrigen;
                $override = $insumo !== null ? $overrides->get($insumo->id) : null;
                $efectivo = $insumo !== null ? $resolver->resolverInsumo($insumo, $override) : null;

                $pesoLinealEf = $efectivo['peso_lineal'] ?? null;
                $pesoDefaultEf = $efectivo['peso_default'] ?? null;
                $kilosRealesEf = ($registro->t_ml_m2 !== null && $pesoLinealEf !== null)
                    ? (float) $registro->t_ml_m2 * $pesoLinealEf
                    : null;

                return [
                    'registro' => $registro,
                    'insumo' => $insumo,
                    'override' => $override,
                    'peso_lineal_ef' => $pesoLinealEf,
                    'peso_default_ef' => $pesoDefaultEf,
                    'kilos_reales_ef' => $kilosRealesEf,
                    'kilos_con_merma' => $mermaCalculator->aplicar($registro, $pesoLinealEf, $pesoDefaultEf),
                ];
            });

        // Agregados por insumo: peso % = (Σ kg c/merma − Σ kg reales) / Σ kg reales (port prepsim).
        $agregados = [];
        foreach ($calc as $c) {
            if ($c['insumo'] === null) {
                continue;
            }
            $id = $c['insumo']->id;
            $agregados[$id]['reales'] = ($agregados[$id]['reales'] ?? 0.0) + (float) ($c['kilos_reales_ef'] ?? 0.0);
            $agregados[$id]['con_merma'] = ($agregados[$id]['con_merma'] ?? 0.0) + (float) $c['kilos_con_merma'];
        }

        // Pasada 2: efectivos de peso % y T. kilos (override del registro, o calculado).
        $registros = $calc->map(function (array $c) use ($agregados) {
            /** @var GeneradoraRegistro $registro */
            $registro = $c['registro'];
            $insumo = $c['insumo'];
            $override = $c['override'];

            // Peso % efectivo: override del registro, o calculado por agregados del mismo insumo.
            // NULL cuando no hay base (registro sin insumo) → la grilla deja la celda en blanco.
            $pesoPorcentualEf = null;
            if ($registro->peso_porcentual !== null) {
                $pesoPorcentualEf = (float) $registro->peso_porcentual;
            } elseif ($insumo !== null && ($agregados[$insumo->id]['reales'] ?? 0.0) > 0) {
                $ag = $agregados[$insumo->id];
                $pesoPorcentualEf = ($ag['con_merma'] - $ag['reales']) / $ag['reales'];
            }

            // T. kilos efectivo: override, o kg reales × (1 + peso %). NULL si no hay kg reales.
            $kilosRealesEf = $c['kilos_reales_ef'];
            $kilosTotalesEf = null;
            if ($registro->kilos_totales !== null) {
                $kilosTotalesEf = (float) $registro->kilos_totales;
            } elseif ($kilosRealesEf !== null) {
                $kilosTotalesEf = $kilosRealesEf * (1 + ($pesoPorcentualEf ?? 0.0));
            }

            return [
                'id' => $registro->id,
                'generadora_id' => $registro->generadora_id,
                'material_origen_id' => $registro->material_origen_id,
                'material' => $registro->material,
                'marca' => $registro->marca,
                'ancho' => $registro->ancho,
                'largo' => $registro->largo,
                'cantidad' => $registro->cantidad,
                'cant_pzas' => $registro->cant_pzas,
                'peso_porcentual' => $registro->peso_porcentual,   // override crudo (null = calculado)
                'peso_porcentual_ef' => $pesoPorcentualEf,         // efectivo mostrado en la grilla
                'kilos_totales' => $registro->kilos_totales,        // override crudo (null = calculado)
                'kilos_totales_ef' => $kilosTotalesEf,             // efectivo (kg c/merma)
                'merma_id' => $registro->merma_id,
                'validado' => $registro->validado,
                't_ml_m2' => $registro->t_ml_m2,
                'kilos_reales' => $c['kilos_reales_ef'],
                'kilos_con_merma' => $c['kilos_con_merma'],
                // Objeto anidado que la grilla usa en sus valueGetter. Los pesos son los EFECTIVOS
                // (override por obra > global); `*_global` permite limpiar el override al reescribir el global.
                'material_origen' => $insumo === null ? null : [
                    'id' => $insumo->id,
                    'descripcion' => $insumo->descripcion,
                    'peso_lineal' => $c['peso_lineal_ef'],
                    'peso_default' => $c['peso_default_ef'],
                    'peso_lineal_global' => $insumo->peso_lineal !== null ? (float) $insumo->peso_lineal : null,
                    'peso_default_global' => $insumo->peso_default !== null ? (float) $insumo->peso_default : null,
                    'peso_lineal_overridden' => $override?->peso_lineal !== null,
                    'peso_default_overridden' => $override?->peso_default !== null,
                ],
                'merma' => $registro->merma?->only(['id', 'descripcion', 'formula']),
            ];
        });

        return Inertia::render('admin/cotiz/generadoras/edit', [
            'generadora' => $generadora,
            'registros' => $registros,
            'insumos' => Insumo::query()
                ->orderBy('descripcion')
                ->get(['id', 'descripcion', 'peso_lineal']),
            'mermas' => Merma::query()
                ->orderBy('descripcion')
                ->get(['id', 'descripcion', 'formula']),
            'lock' => [
                'is_locked' => $generadora->isLocked(),
                'locked_by' => $generadora->lockedBy?->only(['id', 'name']),
                'locked_at' => $generadora->locked_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(GeneradoraUpdateRequest $request, Generadora $generadora): RedirectResponse
    {
        $generadora->update($request->validated());

        return to_route('admin.cotiz.generadoras.edit', $generadora);
    }

    /**
     * Edita un campo de peso (peso_lineal/peso_default) del insumo de un registro como override
     * POR OBRA (obra_insumo_override), no como cambio al catálogo global. Si el valor coincide con
     * el global, limpia ese campo del override (sin diff), igual que prepsim. Visible en el
     * catálogo de obra (Fase 2).
     */
    public function insumoOverride(Request $request, GeneradoraRegistro $registro): RedirectResponse
    {
        $data = $request->validate([
            'field' => ['required', Rule::in(['peso_lineal', 'peso_default'])],
            'value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $registro->loadMissing(['materialOrigen', 'generadora']);
        $insumo = $registro->materialOrigen;
        if ($insumo === null) {
            return back();
        }

        $field = $data['field'];
        $valor = $data['value'];
        $global = $insumo->{$field} !== null ? (float) $insumo->{$field} : null;
        // Coincide con el global (o se vació) → sin override para ese campo.
        $efectivo = ($valor === null || ($global !== null && (float) $valor === $global)) ? null : $valor;

        $override = ObraInsumoOverride::query()->firstOrNew([
            'obra_id' => $registro->generadora->obra_id,
            'insumo_id' => $insumo->id,
        ]);
        $override->{$field} = $efectivo;

        if ($override->estaVacio()) {
            if ($override->exists) {
                $override->delete();
            }
        } else {
            $override->save();
        }

        return back();
    }

    public function destroy(Generadora $generadora): RedirectResponse
    {
        $obraId = $generadora->obra_id;
        $generadora->delete();

        return to_route('admin.cotiz.generadoras.index', $obraId);
    }

    public function reorder(GeneradoraReorderRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->pares() as $par) {
                Generadora::query()
                    ->whereKey($par['id'])
                    ->update(['orden' => $par['orden']]);
            }
        });

        return back();
    }
}
