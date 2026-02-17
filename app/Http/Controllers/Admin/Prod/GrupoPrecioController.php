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
            ->withCount([
                'conceptos as conceptos_count' => fn ($q) => $q->where('activo', true),
                'conceptos as conceptos_sin_precio_count' => fn ($q) => $q->where('activo', true)->whereDoesntHave('grupoPrecioConceptos'),
            ])
            ->when($request->search, fn ($q, $s) => $q->where('no', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%"))
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
        return Inertia::render('admin/prod/grupo-precios/create', [
            'obras' => Obra::orderBy('no')->get(),
            'obraId' => $request->obra_id,
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
        $grupoPrecios = GrupoPrecio::query()
            ->where('obra_id', $obra->id)
            ->with(['grupoPrecioConceptos.concepto'])
            ->withCount('grupoPrecioConceptos')
            ->orderBy('descripcion')
            ->get();

        $unassignedConceptos = Concepto::query()
            ->where('obra_id', $obra->id)
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
            'obras' => Obra::with(['conceptos' => fn ($q) => $q->where('activo', true)->orderBy('marca')])->orderBy('no')->get(),
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
