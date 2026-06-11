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
use App\Services\Cotiz\MermaCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GeneradoraController extends Controller
{
    public function index(Obra $obra): Response
    {
        $generadoras = $obra->generadoras()
            ->withCount('registros')
            ->with('lockedBy:id,name')
            ->orderBy('orden')
            ->get()
            ->map(fn (Generadora $generadora) => [
                'id' => $generadora->id,
                'obra_id' => $generadora->obra_id,
                'titulo' => $generadora->titulo,
                'orden' => $generadora->orden,
                'registros_count' => $generadora->registros_count,
                'is_locked' => $generadora->isLocked(),
                'locked_at' => $generadora->locked_at?->toIso8601String(),
                'locked_by' => $generadora->lockedBy?->only(['id', 'name']),
            ]);

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

    public function edit(Generadora $generadora, MermaCalculator $mermaCalculator): Response
    {
        $generadora->load(['obra', 'lockedBy:id,name']);

        $registros = $generadora->registros()
            ->with(['materialOrigen', 'merma'])
            ->orderBy('id')
            ->get()
            ->map(fn (GeneradoraRegistro $registro) => [
                'id' => $registro->id,
                'generadora_id' => $registro->generadora_id,
                'material_origen_id' => $registro->material_origen_id,
                'material' => $registro->material,
                'marca' => $registro->marca,
                'ancho' => $registro->ancho,
                'largo' => $registro->largo,
                'cantidad' => $registro->cantidad,
                'cant_pzas' => $registro->cant_pzas,
                'peso_porcentual' => $registro->peso_porcentual,
                'kilos_totales' => $registro->kilos_totales,
                'merma_id' => $registro->merma_id,
                'validado' => $registro->validado,
                't_ml_m2' => $registro->t_ml_m2,
                'kilos_reales' => $registro->kilos_reales,
                'kilos_con_merma' => $mermaCalculator->aplicar($registro),
                // Objetos anidados que la grilla usa en sus valueGetter (material/merma).
                'material_origen' => $registro->materialOrigen?->only(['id', 'descripcion', 'peso_lineal']),
                'merma' => $registro->merma?->only(['id', 'descripcion', 'formula']),
            ]);

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
