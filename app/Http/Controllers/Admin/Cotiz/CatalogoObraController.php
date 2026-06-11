<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\ObraFactorOverrideUpdateRequest;
use App\Http\Requests\Admin\Cotiz\ObraInsumoOverrideUpdateRequest;
use App\Models\Cotiz\CentroCosto;
use App\Models\Cotiz\Factor;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraFactorOverride;
use App\Models\Cotiz\ObraInsumoOverride;
use App\Models\Cotiz\Unidad;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Overrides de catálogo por obra (Fase 2): el usuario ajusta P.U./fórmula/datos de
 * insumos y factores solo para una obra, sin tocar el catálogo global. Cada celda
 * editada hace upsert de su campo; si la fila de override queda vacía se borra.
 */
class CatalogoObraController extends Controller
{
    public function index(Obra $obra): Response
    {
        $insumoOverrides = $obra->insumoOverrides()->get()->keyBy('insumo_id');
        $factorOverrides = $obra->factorOverrides()->get()->keyBy('factor_id');

        $insumos = Insumo::query()
            ->with(['unidad:id,descripcion', 'centroCosto:id,cod_coste,concepto'])
            ->orderBy('descripcion')
            ->get()
            ->map(fn (Insumo $insumo) => [
                'insumo' => $insumo,
                'override' => $insumoOverrides->get($insumo->id),
            ]);

        $factores = Factor::query()
            ->with(['insumo:id,descripcion'])
            ->orderBy('codigo')
            ->get()
            ->map(fn (Factor $factor) => [
                'factor' => $factor,
                'override' => $factorOverrides->get($factor->id),
            ]);

        return Inertia::render('admin/cotiz/catalogo-obra/index', [
            'obra' => $obra,
            'insumos' => $insumos,
            'factores' => $factores,
            'unidades' => Unidad::query()->orderBy('descripcion')->get(['id', 'descripcion']),
            'centrosCosto' => CentroCosto::query()->orderBy('concepto')->get(['id', 'cod_coste', 'concepto']),
            'insumosCatalogo' => Insumo::query()->orderBy('descripcion')->get(['id', 'descripcion']),
        ]);
    }

    public function updateInsumo(ObraInsumoOverrideUpdateRequest $request, Obra $obra, Insumo $insumo): RedirectResponse
    {
        $override = ObraInsumoOverride::firstOrNew([
            'obra_id' => $obra->id,
            'insumo_id' => $insumo->id,
        ]);

        $override->fill($this->normalizar($request->validated()));

        if ($override->estaVacio()) {
            if ($override->exists) {
                $override->delete();
            }
        } else {
            $override->save();
        }

        return back();
    }

    public function destroyInsumo(Obra $obra, Insumo $insumo): RedirectResponse
    {
        ObraInsumoOverride::query()
            ->where('obra_id', $obra->id)
            ->where('insumo_id', $insumo->id)
            ->delete();

        return back();
    }

    public function updateFactor(ObraFactorOverrideUpdateRequest $request, Obra $obra, Factor $factor): RedirectResponse
    {
        $override = ObraFactorOverride::firstOrNew([
            'obra_id' => $obra->id,
            'factor_id' => $factor->id,
        ]);

        $override->fill($this->normalizar($request->validated()));

        if ($override->estaVacio()) {
            if ($override->exists) {
                $override->delete();
            }
        } else {
            $override->save();
        }

        return back();
    }

    public function destroyFactor(Obra $obra, Factor $factor): RedirectResponse
    {
        ObraFactorOverride::query()
            ->where('obra_id', $obra->id)
            ->where('factor_id', $factor->id)
            ->delete();

        return back();
    }

    /**
     * Convierte cadenas vacías en NULL para que un campo "limpiado" revierta al global.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizar(array $datos): array
    {
        return array_map(
            fn ($valor) => ($valor === '' ? null : $valor),
            $datos,
        );
    }
}
