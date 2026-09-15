<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\CatalogoStoreRequest;
use App\Http\Requests\Admin\Prod\CatalogoUpdateRequest;
use App\Http\Requests\Admin\Prod\NuevaVersionCatalogoRequest;
use App\Models\Obra;
use App\Models\Prod\Catalogo;
use App\Models\Prod\Proceso;
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
        $catalogo->load(['obra:id,no,descripcion', 'obra.procesos']);

        $procesos = $catalogo->obra?->procesos ?? collect();

        // Las marcas van paginadas y sin sus piezas: un catalogo de obra son
        // decenas de miles de QR, y traerlos todos costaba 9.5 MB de respuesta
        // y cientos de MB de memoria. Los QR de cada marca se piden al
        // desplegarla, contra `prod/marcas/{concepto}/piezas`.
        $marcas = $catalogo->conceptos()
            ->with('categoria')
            ->withCount(['piezas as piezas_count' => fn ($q) => $q->where('activo', true)])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('marca', 'like', "%{$s}%")
                ->orWhere('lote', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%")
                ->orWhereHas('piezas', fn ($p) => $p->where('qs', 'like', "%{$s}%")
                    ->orWhere('qr', 'like', "%{$s}%"))))
            ->orderBy('marca')
            ->orderBy('lote')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/prod/catalogos/show', [
            'catalogo' => $catalogo,
            'marcas' => $marcas,
            // Lo pagado se cuenta por modelo: suma todas sus piezas, de todas
            // las ordenes y versiones, activas o no. El QR cambia con la orden
            // de trabajo y lo pagado bajo un QR viejo sigue siendo del modelo.
            'avancePorMarca' => $this->avancePorMarca((int) $catalogo->obra_id, $marcas->getCollection(), $procesos, $avance),
            'totales' => $this->totalesDelCatalogo($catalogo),
            'procesos' => $procesos->values(),
            'procesosDisponibles' => Proceso::activos()->orderBy('orden')->get(),
            'versiones' => Catalogo::query()
                ->where('obra_id', $catalogo->obra_id)
                ->withCount('conceptos as conceptos_count')
                ->orderByDesc('version')
                ->get(),
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Cuantas piezas equivalentes de cada modelo ya se pagaron en cada proceso.
     *
     * Sale del mapa de la obra, que ya suma liquidaciones y registros por
     * modelo: no hay que cargar piezas.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Concepto>  $marcas
     * @param  \Illuminate\Support\Collection<int, Proceso>  $procesos
     * @return array<int, array<int, float>>
     */
    private function avancePorMarca(int $obraId, $marcas, $procesos, AvanceDePiezas $avance): array
    {
        if ($marcas->isEmpty()) {
            return [];
        }

        $mapa = $avance->mapaDeObra($obraId);
        $resumen = [];

        foreach ($marcas as $marca) {
            foreach ($procesos as $proceso) {
                $resumen[$marca->id][$proceso->id] = $mapa->capturadoDeModelo($marca->claveModelo(), (int) $proceso->id);
            }
        }

        return $resumen;
    }

    /**
     * Los totales del catalogo completo, no de la pagina: al paginar, sumarlos
     * en el navegador solo contaria las 25 marcas a la vista.
     *
     * @return array{marcas: int, piezas: int, declaradas: int, peso: float}
     */
    private function totalesDelCatalogo(Catalogo $catalogo): array
    {
        $marcas = $catalogo->conceptos()
            ->withCount(['piezas as piezas_count' => fn ($q) => $q->where('activo', true)])
            ->get(['id', 'cantidad', 'peso_unitario']);

        return [
            'marcas' => $marcas->count(),
            'piezas' => (int) $marcas->sum('piezas_count'),
            'declaradas' => (int) $marcas->sum('cantidad'),
            'peso' => round($marcas->sum(fn ($m) => $m->piezas_count * (float) $m->peso_unitario), 3),
        ];
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
        if ($catalogo->piezas()->whereHas('registros')->exists()) {
            return back()->withErrors([
                'error' => 'No se puede eliminar un catálogo con piezas que ya tienen producción capturada.',
            ]);
        }

        $catalogo->conceptos()->each(function ($concepto): void {
            $concepto->grupoPrecioConceptos()->delete();
            $concepto->piezas()->delete();
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
