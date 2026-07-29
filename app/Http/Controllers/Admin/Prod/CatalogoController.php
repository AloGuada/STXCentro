<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\CatalogoStoreRequest;
use App\Http\Requests\Admin\Prod\CatalogoUpdateRequest;
use App\Http\Requests\Admin\Prod\NuevaVersionCatalogoRequest;
use App\Models\Obra;
use App\Models\Prod\Catalogo;
use App\Models\Proyecto;
use App\Services\Prod\AvanceDePiezas;
use App\Services\Prod\VersionadorCatalogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogoController extends Controller
{
    public function index(Request $request): Response
    {
        $verTodos = $request->boolean('historicos');

        $catalogos = Catalogo::query()
            ->with('obra:id,no,descripcion')
            ->withCount([
                'conceptos as conceptos_count',
                'conceptos as conceptos_activos_count' => fn ($q) => $q->where('activo', true),
            ])
            ->when(! $verTodos, fn ($q) => $q->where('vigente', true))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('nombre', 'like', "%{$s}%")
                ->orWhereHas('obra', fn ($o) => $o->where('no', 'like', "%{$s}%")
                    ->orWhere('descripcion', 'like', "%{$s}%"))))
            ->orderByDesc('vigente')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/catalogos/index', [
            'catalogos' => $catalogos,
            'obrasDisponibles' => $this->obrasSinCatalogo(),
            'proyectos' => Proyecto::query()
                ->where('activa', true)
                ->orderBy('no')
                ->get(['id', 'no', 'descripcion']),
            'filters' => $request->only(['search', 'historicos']),
        ]);
    }

    public function store(CatalogoStoreRequest $request): RedirectResponse
    {
        if ($request->modo === 'nueva') {
            $obra = Obra::create([
                'no' => $request->obra_no,
                'descripcion' => $request->obra_descripcion,
                'proyecto_id' => $request->proyecto_id,
                'tipo' => 'base',
                'estatus' => 'abierta',
                'activa' => true,
                'es_planta' => false,
            ]);
        } else {
            $obra = Obra::sinPlanta()->findOrFail($request->obra_id);

            if ($obra->catalogos()->exists()) {
                return back()->withErrors([
                    'obra_id' => 'Esa obra ya tiene catálogo. Crea una versión nueva desde el catálogo existente.',
                ]);
            }
        }

        $catalogo = Catalogo::create([
            'obra_id' => $obra->id,
            'nombre' => $request->nombre,
            'notas' => $request->notas,
            'version' => 1,
            'vigente' => true,
        ]);

        return to_route('admin.prod.catalogos.show', $catalogo);
    }

    public function show(Request $request, Catalogo $catalogo, AvanceDePiezas $avance): Response
    {
        $catalogo->load('obra:id,no,descripcion');

        $conceptos = $avance->decorar(
            $catalogo->conceptos()
                ->with('categoria')
                ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('marca', 'like', "%{$s}%")
                    ->orWhere('descripcion', 'like', "%{$s}%")))
                ->orderBy('marca')
                ->get()
        );

        return Inertia::render('admin/prod/catalogos/show', [
            'catalogo' => $catalogo,
            'conceptos' => $conceptos,
            'versiones' => Catalogo::query()
                ->where('obra_id', $catalogo->obra_id)
                ->withCount('conceptos as conceptos_count')
                ->orderByDesc('version')
                ->get(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function update(CatalogoUpdateRequest $request, Catalogo $catalogo): RedirectResponse
    {
        $catalogo->update($request->validated());

        return back();
    }

    /**
     * Congela la versión actual y abre una copia editable como nueva vigente.
     */
    public function nuevaVersion(
        NuevaVersionCatalogoRequest $request,
        Catalogo $catalogo,
        VersionadorCatalogo $versionador,
    ): RedirectResponse {
        if (! $catalogo->vigente) {
            return back()->withErrors([
                'error' => 'Sólo se puede versionar el catálogo vigente.',
            ]);
        }

        $nueva = $versionador->nuevaVersion($catalogo, $request->notas);

        return to_route('admin.prod.catalogos.show', $nueva)
            ->with('success', "Se creó la versión {$nueva->version} con {$nueva->conceptos()->count()} piezas.");
    }

    public function comparar(Catalogo $catalogo, Catalogo $contra, VersionadorCatalogo $versionador): Response
    {
        abort_unless($catalogo->obra_id === $contra->obra_id, 404);

        $catalogo->load('obra:id,no,descripcion');

        return Inertia::render('admin/prod/catalogos/comparar', [
            'catalogo' => $catalogo,
            'contra' => $contra,
            'diff' => $versionador->comparar($catalogo, $contra),
            'versiones' => Catalogo::query()
                ->where('obra_id', $catalogo->obra_id)
                ->orderByDesc('version')
                ->get(['id', 'version', 'nombre', 'vigente']),
        ]);
    }

    public function destroy(Catalogo $catalogo): RedirectResponse
    {
        if ($catalogo->conceptos()->whereHas('registros')->exists()) {
            return back()->withErrors([
                'error' => 'No se puede eliminar un catálogo con piezas que ya tienen producción capturada.',
            ]);
        }

        $catalogo->conceptos()->each(function ($concepto): void {
            $concepto->grupoPrecioConceptos()->delete();
            $concepto->delete();
        });

        $eraVigente = $catalogo->vigente;
        $anterior = $catalogo->origen;
        $catalogo->delete();

        // Al borrar la versión vigente, la anterior vuelve a quedar en uso.
        if ($eraVigente && $anterior) {
            $anterior->update(['vigente' => true]);
        }

        return to_route('admin.prod.catalogos.index');
    }

    /**
     * Obras que todavía no tienen catálogo (las demás sólo admiten versiones).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Obra>
     */
    private function obrasSinCatalogo(): \Illuminate\Database\Eloquent\Collection
    {
        return Obra::query()
            ->sinPlanta()
            ->whereDoesntHave('catalogos')
            ->orderBy('no')
            ->get(['id', 'no', 'descripcion']);
    }
}
