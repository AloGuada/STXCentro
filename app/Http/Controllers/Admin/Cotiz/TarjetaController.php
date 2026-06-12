<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Enums\Cotiz\TipoPintura;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\TarjetaStoreRequest;
use App\Http\Requests\Admin\Cotiz\TarjetaUpdateRequest;
use App\Http\Requests\Admin\Cotiz\TarjetaVincularGeneradoraRequest;
use App\Models\Cotiz\Factor;
use App\Models\Cotiz\Generadora;
use App\Models\Cotiz\GeneradoraRegistro;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\KilosRealesCategoria;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\Tarjeta;
use App\Models\Cotiz\TarjetaCategoriaKilos;
use App\Models\Cotiz\TarjetaRegistro;
use App\Services\Cotiz\TarjetaCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tarjetas de una obra (Fase 3a): CRUD + vínculo con generadoras (importa sus registros).
 * El motor de cálculo y la grilla densa llegan en 3b/3c.
 */
class TarjetaController extends Controller
{
    public function index(Request $request, Obra $obra, TarjetaCalculator $calculator): Response
    {
        $me = $request->user()?->id;

        $tarjetas = $obra->tarjetas()
            ->withCount(['registros', 'generadoras'])
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->map(function (Tarjeta $tarjeta) use ($calculator, $me) {
                // Recalcula en vivo y refresca el cache M039 (igual que prepsim, para que
                // el Resumen no dependa de abrir cada tarjeta).
                $totales = $calculator->refrescarCache($tarjeta);

                // El lock propio no se muestra como bloqueo: uno siempre puede re-entrar a lo suyo.
                // Solo bloquea (badge + acciones) si lo edita OTRO usuario.
                $bloqueadaPorOtro = $me !== null && $tarjeta->isLocked() && ! $tarjeta->isLockedBy($me);

                return [
                    'id' => $tarjeta->id,
                    'obra_id' => $tarjeta->obra_id,
                    'descripcion' => $tarjeta->descripcion,
                    'orden' => $tarjeta->orden,
                    'registros_count' => $tarjeta->registros_count,
                    'generadoras_count' => $tarjeta->generadoras_count,
                    'importe_materiales' => $totales['total_importe'],
                    'kilos_reales' => $totales['kg_reales_total'],
                    'is_locked' => $bloqueadaPorOtro,
                    'locked_by' => $bloqueadaPorOtro ? $tarjeta->lockedBy?->only(['id', 'name']) : null,
                ];
            });

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

    public function edit(Tarjeta $tarjeta, TarjetaCalculator $calculator): Response
    {
        $tarjeta->load(['obra', 'lockedBy:id,name', 'generadoras:id,titulo,orden']);

        $vinculadas = $tarjeta->generadoras->pluck('id');
        $resultado = $calculator->refrescarCache($tarjeta);

        $estructuras = $tarjeta->estructuras()->orderBy('orden')->orderBy('id')->get(['id', 'nombre', 'orden']);
        $categoriasKilos = $tarjeta->categoriasKilos()
            ->with('categoria:id,descripcion,tipo_corte')
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->map(fn (TarjetaCategoriaKilos $c) => [
                'id' => $c->id,
                'categoria_id' => $c->categoria_id,
                'descripcion' => $c->categoria?->descripcion,
                'tipo_corte' => $c->categoria?->tipo_corte?->value,
                'porcentual' => $c->porcentual,
                'orden' => $c->orden,
            ]);

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
                'registros_count' => count($resultado['registros']),
            ],
            'registros' => $resultado['registros'],
            'factores' => $resultado['factores'],
            'estructuras' => $estructuras,
            'categoriasKilos' => $categoriasKilos,
            'celdas' => $tarjeta->kilosReales()->get(['id', 'categoria_id', 'estructura_id', 'kilos']),
            'preciosOverride' => $tarjeta->insumoPrecios()->pluck('precio_unitario', 'insumo_id'),
            'totales' => [
                'total_importe' => $resultado['total_importe'],
                'total_registros' => $resultado['total_registros'],
                'total_factores' => $resultado['total_factores'],
                'kg_fab' => $resultado['kg_fab'],
                'area_pintura' => $resultado['area_pintura'],
                'kg_reales_total' => $resultado['kg_reales_total'],
            ],
            'catalogos' => [
                'insumos' => Insumo::query()->orderBy('descripcion')->get(['id', 'descripcion', 'precio_unitario']),
                'factores' => Factor::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre', 'formula']),
                'krCategorias' => KilosRealesCategoria::query()->orderBy('orden')->get(['id', 'descripcion', 'tipo_corte']),
                'tiposPintura' => TipoPintura::options(),
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
