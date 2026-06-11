<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\TarjetaStoreRequest;
use App\Http\Requests\Admin\Cotiz\TarjetaUpdateRequest;
use App\Http\Requests\Admin\Cotiz\TarjetaVincularGeneradoraRequest;
use App\Models\Cotiz\Generadora;
use App\Models\Cotiz\GeneradoraRegistro;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\Tarjeta;
use App\Models\Cotiz\TarjetaRegistro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tarjetas de una obra (Fase 3a): CRUD + vínculo con generadoras (importa sus registros).
 * El motor de cálculo y la grilla densa llegan en 3b/3c.
 */
class TarjetaController extends Controller
{
    public function index(Obra $obra): Response
    {
        $tarjetas = $obra->tarjetas()
            ->withCount(['registros', 'generadoras'])
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->map(fn (Tarjeta $tarjeta) => [
                'id' => $tarjeta->id,
                'obra_id' => $tarjeta->obra_id,
                'descripcion' => $tarjeta->descripcion,
                'orden' => $tarjeta->orden,
                'registros_count' => $tarjeta->registros_count,
                'generadoras_count' => $tarjeta->generadoras_count,
                'importe_materiales' => $tarjeta->importe_materiales,
                'kilos_reales' => $tarjeta->kilos_reales,
                'is_locked' => $tarjeta->isLocked(),
                'locked_by' => $tarjeta->lockedBy?->only(['id', 'name']),
            ]);

        return Inertia::render('admin/cotiz/tarjetas/index', [
            'obra' => $obra,
            'tarjetas' => $tarjetas,
            'generadoras' => $obra->generadoras()
                ->withCount('registros')
                ->orderBy('orden')
                ->get(['id', 'titulo', 'orden']),
        ]);
    }

    public function store(TarjetaStoreRequest $request): RedirectResponse
    {
        $tarjeta = DB::transaction(function () use ($request) {
            $tarjeta = Tarjeta::create($request->safe()->only(['obra_id', 'descripcion', 'orden']));

            if ($request->filled('generadora_id')) {
                $this->vincular($tarjeta, (int) $request->integer('generadora_id'));
            }

            return $tarjeta;
        });

        return to_route('admin.cotiz.tarjetas.edit', $tarjeta);
    }

    public function edit(Tarjeta $tarjeta): Response
    {
        $tarjeta->load(['obra', 'lockedBy:id,name', 'generadoras:id,titulo,orden']);

        $vinculadas = $tarjeta->generadoras->pluck('id');

        return Inertia::render('admin/cotiz/tarjetas/edit', [
            'tarjeta' => [
                'id' => $tarjeta->id,
                'obra_id' => $tarjeta->obra_id,
                'descripcion' => $tarjeta->descripcion,
                'orden' => $tarjeta->orden,
                'importe_materiales' => $tarjeta->importe_materiales,
                'kilos_reales' => $tarjeta->kilos_reales,
                'obra' => $tarjeta->obra,
                'generadoras' => $tarjeta->generadoras->map->only(['id', 'titulo', 'orden']),
                'registros_count' => $tarjeta->registros()->count(),
            ],
            'generadorasDisponibles' => $tarjeta->obra->generadoras()
                ->whereNotIn('id', $vinculadas)
                ->withCount('registros')
                ->orderBy('orden')
                ->get(['id', 'titulo', 'orden']),
            'lock' => [
                'is_locked' => $tarjeta->isLocked(),
                'locked_by' => $tarjeta->lockedBy?->only(['id', 'name']),
                'locked_at' => $tarjeta->locked_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(TarjetaUpdateRequest $request, Tarjeta $tarjeta): RedirectResponse
    {
        $tarjeta->update($request->validated());

        return back();
    }

    public function destroy(Tarjeta $tarjeta): RedirectResponse
    {
        $obraId = $tarjeta->obra_id;
        $tarjeta->delete();

        return to_route('admin.cotiz.tarjetas.index', $obraId);
    }

    public function vincularGeneradora(TarjetaVincularGeneradoraRequest $request, Tarjeta $tarjeta): RedirectResponse
    {
        DB::transaction(function () use ($request, $tarjeta) {
            $this->vincular($tarjeta, (int) $request->integer('generadora_id'));
        });

        return back();
    }

    public function desvincularGeneradora(Tarjeta $tarjeta, Generadora $generadora): RedirectResponse
    {
        DB::transaction(function () use ($tarjeta, $generadora) {
            // Borra los registros importados de esta generadora antes de soltar el vínculo.
            $tarjeta->registros()
                ->whereIn(
                    'generadora_registro_id',
                    $generadora->registros()->select('id'),
                )
                ->delete();

            $tarjeta->generadoras()->detach($generadora->id);
        });

        return back();
    }

    /**
     * Vincula una generadora a la tarjeta e importa sus registros (los que tengan material
     * de origen y no estén ya importados en ninguna tarjeta — el UNIQUE lo garantiza).
     */
    private function vincular(Tarjeta $tarjeta, int $generadoraId): void
    {
        $tarjeta->generadoras()->syncWithoutDetaching([$generadoraId]);

        $yaImportados = TarjetaRegistro::query()
            ->whereNotNull('generadora_registro_id')
            ->pluck('generadora_registro_id');

        $registros = GeneradoraRegistro::query()
            ->where('generadora_id', $generadoraId)
            ->whereNotNull('material_origen_id')
            ->whereNotIn('id', $yaImportados)
            ->pluck('id');

        $tarjeta->registros()->createMany(
            $registros->map(fn (int $id) => [
                'generadora_registro_id' => $id,
                'validado' => false,
                'tipo_pintura' => 'auto',
            ])->all(),
        );
    }
}
