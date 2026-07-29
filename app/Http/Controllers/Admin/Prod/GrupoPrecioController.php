<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\GrupoPrecioStoreRequest;
use App\Http\Requests\Admin\Prod\GrupoPrecioUpdateRequest;
use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioConcepto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GrupoPrecioController extends Controller
{
    public function index(Request $request): Response
    {
        $obras = Obra::query()
            ->sinPlanta()
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
            'obras' => $obra ? [] : Obra::sinPlanta()->orderBy('no')->get(['id', 'no', 'descripcion']),
        ]);
    }

    public function store(GrupoPrecioStoreRequest $request): RedirectResponse
    {
        GrupoPrecio::create([
            'obra_id' => $request->obra_id,
            'descripcion' => $request->descripcion,
            'precio_kilo' => $request->precio_kilo,
        ]);

        return to_route('admin.prod.grupo-precios.show-by-obra', $request->obra_id);
    }

    public function showByObra(Obra $obra): Response
    {
        abort_if($obra->es_planta, 404);

        $grupoPrecios = GrupoPrecio::query()
            ->where('obra_id', $obra->id)
            ->with(['grupoPrecioConceptos.concepto'])
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
        ]);
    }

    public function edit(GrupoPrecio $grupoPrecio): Response
    {
        $grupoPrecio->load('grupoPrecioConceptos.concepto.obra');

        return Inertia::render('admin/prod/grupo-precios/edit', [
            'grupoPrecio' => $grupoPrecio,
            'obras' => Obra::sinPlanta()->with(['conceptos' => fn ($q) => $q->deCatalogoVigente()->where('activo', true)->orderBy('marca')])->orderBy('no')->get(),
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
            'precio_kilo' => $request->precio_kilo,
        ]);

        return to_route('admin.prod.grupo-precios.show-by-obra', $grupoPrecio->obra_id);
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
