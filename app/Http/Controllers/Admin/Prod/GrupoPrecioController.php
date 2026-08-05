<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\GrupoPrecioStoreRequest;
use App\Http\Requests\Admin\Prod\GrupoPrecioUpdateRequest;
use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\GrupoPrecioProceso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GrupoPrecioController extends Controller
{
    public function index(Request $request): Response
    {
        // Sólo obras con catálogo: sin marcas no hay nada a qué ponerle precio.
        $obras = Obra::query()
            ->sinPlanta()
            ->whereHas('catalogos', fn ($q) => $q->where('vigente', true))
            ->withCount([
                'conceptos as conceptos_count' => fn ($q) => $q->deCatalogoVigente()->where('activo', true),
                'conceptos as conceptos_sin_precio_count' => fn ($q) => $q->deCatalogoVigente()->where('activo', true)->whereDoesntHave('grupoPrecioConceptos'),
            ])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('no', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%")))
            ->orderBy('no')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/grupo-precios/index', [
            'obras' => $obras,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(Request $request): Response
    {
        // Si se llega desde una obra, se da por dada y no se vuelve a elegir.
        $obra = $request->obra_id
            ? Obra::sinPlanta()->find($request->obra_id)
            : null;

        return Inertia::render('admin/prod/grupo-precios/create', [
            'obra' => $obra,
            'obras' => $obra ? [] : Obra::sinPlanta()
                ->whereHas('catalogos', fn ($q) => $q->where('vigente', true))
                ->orderBy('no')
                ->get(['id', 'no', 'descripcion']),
            'procesos' => $this->procesosDeObra($obra),
        ]);
    }

    public function store(GrupoPrecioStoreRequest $request): RedirectResponse
    {
        $grupoPrecio = GrupoPrecio::create([
            'obra_id' => $request->obra_id,
            'descripcion' => $request->descripcion,
        ]);

        $this->guardarTarifas($grupoPrecio, $request->input('precios', []));

        return to_route('admin.prod.grupo-precios.show-by-obra', $request->obra_id);
    }

    public function showByObra(Obra $obra): Response
    {
        abort_if($obra->es_planta, 404);

        $grupoPrecios = GrupoPrecio::query()
            ->where('obra_id', $obra->id)
            ->with(['grupoPrecioConceptos.concepto', 'precios.proceso'])
            ->withCount('grupoPrecioConceptos')
            ->orderBy('descripcion')
            ->get();

        $unassignedConceptos = Concepto::query()
            ->where('obra_id', $obra->id)
            ->deCatalogoVigente()
            ->where('activo', true)
            ->whereDoesntHave('grupoPrecioConceptos')
            ->orderBy('marca')
            ->get();

        return Inertia::render('admin/prod/grupo-precios/show', [
            'obra' => $obra,
            'grupoPrecios' => $grupoPrecios,
            'unassignedConceptos' => $unassignedConceptos,
            'procesos' => $this->procesosDeObra($obra),
        ]);
    }

    public function edit(GrupoPrecio $grupoPrecio): Response
    {
        $grupoPrecio->load(['grupoPrecioConceptos.concepto.obra', 'precios', 'obra']);

        return Inertia::render('admin/prod/grupo-precios/edit', [
            'grupoPrecio' => $grupoPrecio,
            'obras' => Obra::sinPlanta()
                ->whereHas('catalogos', fn ($q) => $q->where('vigente', true))
                ->with(['conceptos' => fn ($q) => $q->deCatalogoVigente()->where('activo', true)->orderBy('marca')])
                ->orderBy('no')
                ->get(),
            'procesos' => $this->procesosDeObra($grupoPrecio->obra),
        ]);
    }

    public function assignConceptos(Request $request, GrupoPrecio $grupoPrecio): RedirectResponse
    {
        $request->validate([
            'concepto_ids' => ['required', 'array', 'min:1'],
            'concepto_ids.*' => ['exists:conceptos,id'],
        ]);

        foreach ($request->concepto_ids as $conceptoId) {
            GrupoPrecioConcepto::firstOrCreate([
                'concepto_id' => $conceptoId,
                'grupo_precio_id' => $grupoPrecio->id,
            ]);
        }

        return back()->with('success', 'Conceptos asignados correctamente.');
    }

    public function update(GrupoPrecioUpdateRequest $request, GrupoPrecio $grupoPrecio): RedirectResponse
    {
        $grupoPrecio->update([
            'obra_id' => $request->obra_id,
            'descripcion' => $request->descripcion,
        ]);

        $this->guardarTarifas($grupoPrecio, $request->input('precios', []));

        return to_route('admin.prod.grupo-precios.show-by-obra', $grupoPrecio->obra_id);
    }

    /**
     * Procesos que la obra paga: son los unicos que necesitan tarifa.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Prod\Proceso>
     */
    private function procesosDeObra(?Obra $obra): \Illuminate\Support\Collection
    {
        return $obra === null ? collect() : $obra->procesos()->where('activo', true)->get();
    }

    /**
     * Una tarifa por proceso. Los procesos que la obra no paga se ignoran, para
     * que un grupo no arrastre precios de trabajo que ahi no se hace.
     *
     * @param  array<int|string, mixed>  $precios  procesoId => precio por kilo
     */
    private function guardarTarifas(GrupoPrecio $grupoPrecio, array $precios): void
    {
        $permitidos = $this->procesosDeObra($grupoPrecio->obra)->pluck('id');

        foreach ($precios as $procesoId => $precioKilo) {
            if (! $permitidos->contains((int) $procesoId)) {
                continue;
            }

            GrupoPrecioProceso::updateOrCreate(
                ['grupo_precio_id' => $grupoPrecio->id, 'proceso_id' => (int) $procesoId],
                ['precio_kilo' => (float) $precioKilo],
            );
        }
    }

    public function destroy(GrupoPrecio $grupoPrecio): RedirectResponse
    {
        if ($grupoPrecio->grupoPrecioConceptos()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar un grupo de precios que tiene conceptos asignados.']);
        }

        $obraId = $grupoPrecio->obra_id;
        $grupoPrecio->delete();

        return to_route('admin.prod.grupo-precios.show-by-obra', $obraId);
    }
}
